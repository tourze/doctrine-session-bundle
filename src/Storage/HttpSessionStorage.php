<?php

namespace Tourze\DoctrineSessionBundle\Storage;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Tourze\DoctrineSessionBundle\Exception\InvalidArgumentException;
use Tourze\DoctrineSessionBundle\Exception\LogicException;
use Tourze\DoctrineSessionBundle\Service\PdoSessionHandler;

#[WithMonologChannel(channel: 'doctrine_session')]
#[Autoconfigure(public: true)]
class HttpSessionStorage implements SessionStorageInterface
{
    /**
     * @var SessionBagInterface[]
     */
    protected array $bags = [];

    protected bool $started = false;

    protected bool $closed = false;

    /** @var array<string, mixed> */
    private array $initSession = [];

    /** @var array<string, mixed> */
    private array $currentSession = [];

    private MetadataBag $metadataBag;

    /**
     * HTTP会话存储构造函数.
     *
     * 根据存储驱动的行为需求，可能需要完全重写此构造函数。
     *
     * 支持的会话配置选项列表（省略'session.'前缀）：
     *
     * @see https://php.net/session.configuration 完整配置选项
     *
     * - cache_limiter: "" (使用"0"完全阻止发送headers)
     * - cache_expire: "0"
     * - cookie_domain: ""
     * - cookie_httponly: ""
     * - cookie_lifetime: "0"
     * - cookie_path: "/"
     * - cookie_secure: ""
     * - cookie_samesite: null
     * - gc_divisor: "100"
     * - gc_maxlifetime: "1440"
     * - gc_probability: "1"
     * - lazy_write: "1"
     * - name: "PHPSESSID"
     * - referer_check: ""
     * - serialize_handler: "php"
     * - use_strict_mode: "1"
     * - use_cookies: "1"
     * - use_only_cookies: "1"
     * - use_trans_sid: "0"
     * - sid_length: "32"
     * - sid_bits_per_character: "5"
     * - trans_sid_hosts: $_SERVER['HTTP_HOST']
     * - trans_sid_tags: "a=href,area=href,frame=src,form="
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PdoSessionHandler $handler,
        string $sessionName,
        ?string $sessionId = null,
        private readonly ?Request $request = null,
        ?MetadataBag $metaBag = null,
    ) {
        $this->name = $sessionName;
        if (null !== $sessionId) {
            $this->setId($sessionId);
        }
        $this->setMetadataBag($metaBag);

        // 默认注册AttributeBag，以保持向后兼容性
        $this->registerBag(new AttributeBag());
    }

    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        $data = $this->handler->read($this->getId());
        $this->currentSession = $this->initSession = $this->restoreSessionFromSerializedData($data);
        $this->loadSession();

        return true;
    }

    /**
     * 从序列化数据恢复会话数组.
     *
     * @return array<string, mixed>
     */
    private function restoreSessionFromSerializedData(string $data): array
    {
        if ('' === $data) {
            return [];
        }

        try {
            $unserialized = unserialize($data);

            if (!is_array($unserialized)) {
                $this->logger->warning('Unserialized session data is not an array', [
                    'type' => get_debug_type($unserialized),
                    'id' => $this->getId(),
                ]);

                return [];
            }

            $validArray = $this->filterValidSessionKeys($unserialized);

            $this->logger->debug('初始化请求得到会话信息', [
                'data' => $data,
                'id' => $this->getId(),
            ]);

            return $validArray;
        } catch (\Throwable $exception) {
            $this->logger->error('反序列化SESSION数据时发生错误', [
                'exception' => $exception,
                'data' => $data,
            ]);

            return [];
        }
    }

    /**
     * 过滤并验证会话键必须是字符串.
     *
     * @param array<mixed, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function filterValidSessionKeys(array $data): array
    {
        $validArray = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $validArray[$key] = $value;
            } else {
                $this->logger->warning('Session data contains non-string key', [
                    'keyType' => get_debug_type($key),
                    'id' => $this->getId(),
                ]);
            }
        }

        return $validArray;
    }

    private ?string $id = null;

    public function getId(): string
    {
        if (null === $this->id) {
            // 优先从提供的request中获取sessionId
            if (null !== $this->request) {
                $id = $this->request->cookies->get($this->getName());
                if (is_string($id) && '' !== $id) {
                    $this->setId($id);
                    assert(null !== $this->id);

                    return $this->id;
                }
            }

            // 生成新的sessionId
            $this->setId(hash('md5', random_bytes(16)));
        }

        // $this->id 在 setId() 后保证非null
        assert(null !== $this->id);

        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    private string $name;

    public function getName(): string
    {
        // 默认是这个
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function regenerate(bool $destroy = false, ?int $lifetime = null): bool
    {
        $oldId = $this->id;

        if ($destroy && null !== $oldId) {
            $this->handler->destroy($oldId);
            $this->metadataBag->stampNew();
        }

        $this->setId(hash('md5', random_bytes(16)));

        return true;
    }

    public function save(): void
    {
        if ($this->currentSession === $this->initSession) {
            $this->logger->debug('会话数据无变化，不重新写数据库');

            return;
        }

        // 存储副本以便在会话非空时可以恢复bags
        $session = $this->currentSession;

        foreach ($this->bags as $bag) {
            if (!isset($session[$key = $bag->getStorageKey()]) || [] === $session[$key]) {
                unset($session[$key]);
            }
        }
        if ([] !== $session && [$key = $this->metadataBag->getStorageKey()] === array_keys($session)) {
            unset($session[$key]);
        }

        $this->logger->debug('保存会话数据到数据库', [
            'id' => $this->getId(),
            'data' => $session,
        ]);
        $this->handler->write($this->getId(), serialize($session));

        $this->closed = true;
        $this->started = false;
    }

    public function clear(): void
    {
        // 清除所有bags
        foreach ($this->bags as $bag) {
            $bag->clear();
        }

        // 清除会话数据
        $this->currentSession = [];

        // 重新连接bags到会话
        $this->loadSession();
    }

    public function registerBag(SessionBagInterface $bag): void
    {
        if ($this->started) {
            throw new LogicException('Cannot register a bag when the session is already started.');
        }

        $this->bags[$bag->getName()] = $bag;
    }

    public function getBag(string $name): SessionBagInterface
    {
        if (!isset($this->bags[$name])) {
            throw new InvalidArgumentException(sprintf('The SessionBagInterface "%s" is not registered.', $name));
        }

        if (!$this->started) {
            $this->start();
            $this->loadSession();
        }

        return $this->bags[$name];
    }

    public function setMetadataBag(?MetadataBag $metaBag = null): void
    {
        $this->metadataBag = $metaBag ?? new MetadataBag();
    }

    public function getMetadataBag(): MetadataBag
    {
        return $this->metadataBag;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function destroy(): bool
    {
        $this->handler->destroy($this->getId());
        $this->currentSession = [];
        $this->initSession = [];
        $this->started = false;
        $this->closed = true;

        return true;
    }

    /**
     * 加载会话属性数据.
     *
     * 启动会话后，PHP从配置的处理器中检索会话数据
     * （可能是PHP内置处理器或通过session_set_save_handler()设置的自定义处理器）。
     * PHP获取read()处理器的返回值，反序列化并自动填充到$_SESSION中。
     */
    protected function loadSession(): void
    {
        $session = $this->currentSession;

        $bags = array_merge($this->bags, [$this->metadataBag]);

        foreach ($bags as $bag) {
            $key = $bag->getStorageKey();
            $session[$key] = isset($session[$key]) && \is_array($session[$key]) ? $session[$key] : [];
            $bag->initialize($session[$key]);
        }

        $this->currentSession = $session;
        $this->started = true;
        $this->closed = false;
    }
}

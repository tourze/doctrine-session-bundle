# Doctrine ORM Session Bundle 实现计划

## 概述

基于 [Symfony Issue #58257](https://github.com/symfony/symfony/issues/58257) 的需求，我们需要实现一个完全自定义的Session机制，不依赖PHP内置的session处理，而是使用Doctrine ORM来管理session数据。

## 核心需求

1. **完全自定义Session处理**：不使用PHP内置的session机制
2. **Doctrine ORM存储**：使用Entity来管理session数据
3. **独立的数据库连接**：避免与正常业务查询冲突
4. **自定义Cookie管理**：完全控制session cookie的读写
5. **支持JSON数据格式**：便于调试和扩展
6. **支持自定义字段类型**：如UUID主键、datetime字段等

**难点** 怎么自动创建 Doctrine 连接资源，怎么管理好。

## 技术架构

### 1. 核心组件设计

```ascii
DoctrineSessionBundle/
├── Entity/
│   └── Session.php                    # Session实体
├── Repository/
│   └── SessionRepository.php          # Session仓库
├── Handler/
│   └── DoctrineORMSessionHandler.php  # Session处理器
├── Storage/
│   └── DoctrineORMSessionStorage.php  # Session存储
├── Factory/
│   └── SessionFactory.php             # Session工厂
├── EventSubscriber/
│   └── SessionSubscriber.php          # Session事件订阅器
├── DependencyInjection/
│   ├── Configuration.php              # 配置类
│   └── DoctrineORMSessionExtension.php
└── Resources/config/
    └── services.yaml
```

### 2. 数据库设计

#### Session Entity 字段设计

- `id`: UUID主键 (支持自定义类型)
- `data`: JSON格式的session数据
- `createdTime`: 创建时间 (DateTime)
- `updatedTime`: 更新时间 (DateTime)
- `expiresTime`: 过期时间 (DateTime)
- `ipAddress`: IP地址 (可选)
- `userAgent`: 用户代理 (可选)

### 3. 实现细节

#### 3.1 Session Entity

```php
#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: 'doctrine_orm_sessions')]
#[ORM\Index(columns: ['expires_time'], name: 'idx_session_expires')]
#[ORM\Index(columns: ['created_time'], name: 'idx_session_created')]
class Session
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdTime;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedTime;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresTime;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;
}
```

#### 3.2 独立数据库连接

创建专用的EntityManager和Connection：

```yaml
# services.yaml
doctrine.dbal.session_connection:
    class: Doctrine\DBAL\Connection
    factory: ['@doctrine.dbal.connection_factory', 'createConnection']
    arguments:
        - '%doctrine.dbal.session_connection.params%'
        - '@doctrine.dbal.configuration'
        - '@doctrine.dbal.session_connection.event_manager'

doctrine.orm.session_entity_manager:
    class: Doctrine\ORM\EntityManager
    factory: ['@doctrine.orm.entity_manager.factory', 'create']
    arguments:
        - '@doctrine.dbal.session_connection'
        - '@doctrine.orm.session_configuration'
```

#### 3.3 Session Handler

实现 `SessionHandlerInterface`：

```php
class DoctrineORMSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SessionRepository $sessionRepository,
        private readonly LoggerInterface $logger,
        private readonly int $maxLifetime = 1440
    ) {}

    public function open(string $path, string $name): bool
    public function close(): bool
    public function read(string $id): string|false
    public function write(string $id, string $data): bool
    public function destroy(string $id): bool
    public function gc(int $max_lifetime): int|false
}
```

#### 3.4 Session Storage

自定义Session存储，不依赖PHP内置机制：

```php
class DoctrineORMSessionStorage implements SessionStorageInterface
{
    private bool $started = false;
    private string $id = '';
    private string $name = 'DOCTRINE_ORM_SESSID';
    private array $data = [];

    public function start(): bool
    public function isStarted(): bool
    public function getId(): string
    public function setId(string $id): void
    public function getName(): string
    public function setName(string $name): void
    public function regenerate(bool $destroy = false, int $lifetime = null): bool
    public function save(): void
    public function clear(): void
}
```

#### 3.5 Cookie管理

完全自定义Cookie的读写：

```php
class SessionSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        // 从Cookie中读取session ID
        $request = $event->getRequest();
        $sessionId = $request->cookies->get($this->sessionName);

        // 设置到session storage
        if ($sessionId) {
            $this->sessionStorage->setId($sessionId);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // 写入session cookie
        $response = $event->getResponse();
        $sessionId = $this->sessionStorage->getId();

        if ($sessionId && $this->sessionStorage->isStarted()) {
            $cookie = Cookie::create(
                $this->sessionName,
                $sessionId,
                $this->calculateExpireTime(),
                $this->cookiePath,
                $this->cookieDomain,
                $this->cookieSecure,
                $this->cookieHttpOnly,
                false,
                $this->cookieSameSite
            );

            $response->headers->setCookie($cookie);
        }
    }
}
```

### 4. 配置选项

```yaml
# config/packages/doctrine_orm_session.yaml
doctrine_orm_session:
    # 数据库连接配置
    connection:
        driver: 'pdo_mysql'
        host: '%env(DATABASE_HOST)%'
        port: '%env(DATABASE_PORT)%'
        dbname: '%env(DATABASE_NAME)%'
        user: '%env(DATABASE_USER)%'
        password: '%env(DATABASE_PASSWORD)%'

    # Session配置
    session:
        name: 'DOCTRINE_ORM_SESSID'
        lifetime: 1440  # 24分钟
        cookie_path: '/'
        cookie_domain: null
        cookie_secure: false
        cookie_httponly: true
        cookie_samesite: 'lax'

    # 实体配置
    entity:
        class: 'Tourze\DoctrineSessionBundle\Entity\Session'
        table_name: 'doctrine_orm_sessions'
        id_type: 'uuid'  # 支持 uuid, string, integer

    # 垃圾回收
    gc:
        probability: 1
        divisor: 100
        max_lifetime: 1440
```

### 5. 实现步骤

#### 阶段1：基础架构

1. 创建Session Entity
2. 创建SessionRepository
3. 配置独立的EntityManager和Connection
4. 实现基础的Configuration类

#### 阶段2：核心功能

1. 实现DoctrineORMSessionHandler
2. 实现DoctrineORMSessionStorage
3. 创建SessionFactory
4. 实现基础的读写功能

#### 阶段3：Cookie管理

1. 实现SessionSubscriber
2. 完全自定义Cookie读写
3. 处理session生命周期事件

#### 阶段4：高级功能

1. 垃圾回收机制
2. Session数据加密（可选）
3. IP和UserAgent跟踪
4. 性能优化（缓存等）

#### 阶段5：测试和文档

1. 单元测试
2. 集成测试
3. 性能测试
4. 文档编写

### 6. 关键技术点

#### 6.1 避免与正常查询冲突

- 使用独立的EntityManager实例
- 使用独立的数据库连接
- 可选择使用不同的数据库

#### 6.2 数据序列化

- 使用JSON格式存储session数据
- 支持复杂数据结构
- 便于调试和数据迁移

#### 6.3 性能优化

- 合理的数据库索引
- 延迟垃圾回收
- 可选的缓存层

#### 6.4 安全考虑

- Session ID的安全生成
- 可选的数据加密
- IP和UserAgent验证
- CSRF保护

### 7. 兼容性

- PHP 8.1+
- Symfony 6.4+
- Doctrine ORM 3.0+
- 支持MySQL、PostgreSQL、SQLite等数据库

### 8. 扩展性

- 支持自定义Session Entity
- 支持自定义字段类型
- 支持自定义序列化器
- 支持插件化的功能扩展

## 总结

这个实现方案完全脱离了PHP内置的session机制，提供了一个基于Doctrine ORM的完整Session解决方案。通过独立的数据库连接和自定义的Cookie管理，确保了与现有业务逻辑的隔离，同时提供了更好的可扩展性和可维护性。

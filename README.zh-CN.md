# Doctrine Session Bundle

[English](README.md) | [中文](README.zh-CN.md)

基于 Doctrine DBAL 的 Symfony HTTP 会话管理 Bundle。

## 特性

- 基于 PDO 的会话存储与数据库持久化
- Redis/缓存集成，提升性能
- HTTP 感知的会话管理
- 自动垃圾回收
- 数据库 Schema 管理集成

## 命令

### doctrine:session:gc

清理数据库中的过期会话记录。

**用法：**
```bash
php bin/console doctrine:session:gc
```

**说明：**
- 自动从 sessions 表中删除过期的会话记录
- 使用会话生命周期配置来判断过期会话
- 提供清理记录数量的详细反馈
- 可在生产环境中安全运行

**示例输出：**
```
清理过期会话
=========================

[OK] 成功清理了 42 个过期的 session 记录
```

## 配置

该 Bundle 集成 Doctrine DBAL 提供持久化会话存储。配置数据库连接后，Bundle 会自动创建和管理 sessions 表。

### 环境变量

- `DOCTRINE_SESSION_NAME` (可选，默认值: `PHPSESSID`): 应用使用的会话 Cookie 名称

## 测试

运行测试套件：
```bash
vendor/bin/phpunit packages/doctrine-session-bundle
```
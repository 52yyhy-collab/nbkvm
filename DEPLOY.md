# NBKVM 部署说明
## 1. 本地开发部署
直接运行：
```bash
bash bin/deploy_local.sh
```
然后启动：
```bash
php -S 0.0.0.0:8080 -t public
```
## 2. SQLite 模式
默认直接可用：
```bash
php bin/init_db.php
```
## 3. MySQL 模式
先准备数据库：
```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS nbkvm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'nbkvm'@'localhost' IDENTIFIED BY 'nbkvm';"
sudo mysql -e "GRANT ALL PRIVILEGES ON nbkvm.* TO 'nbkvm'@'localhost'; FLUSH PRIVILEGES;"
```
再导出环境变量：
```bash
export NBKVM_DB_DRIVER=mysql
export NBKVM_DB_HOST=127.0.0.1
export NBKVM_DB_PORT=3306
export NBKVM_DB_NAME=nbkvm
export NBKVM_DB_USER=nbkvm
export NBKVM_DB_PASS=nbkvm
php bin/init_db.php
```
## 4. noVNC
### 方式 A：已有 noVNC 服务
配置：
```bash
export NBKVM_NOVNC_BASE_URL='http://your-host:6080'
```
### 方式 B：按虚拟机临时起代理
```bash
bash bin/start_novnc_proxy.sh <虚拟机名称> 6080
```
## 5. 权限建议
- 运行 Web 的用户需要有 libvirt 访问权限
- 典型做法：加入 `libvirt` / `kvm` 组
```bash
sudo usermod -aG libvirt,kvm <你的用户>
```
重新登录会话后生效。
## 6. 生产建议
- 用 Nginx / Apache 反向代理 PHP
- 开启 HTTPS
- 修改默认 admin 密码
- 使用 MySQL / MariaDB
- 限制管理入口仅内网可达
- 定期备份数据库和镜像目录

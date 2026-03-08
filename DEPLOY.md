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
## 4. 控制台组件
### noVNC
#### 方式 A：已有 noVNC 服务
配置：
```bash
export NBKVM_NOVNC_BASE_URL='http://your-host:6080'
```
#### 方式 B：按虚拟机临时起代理
```bash
bash bin/start_novnc_proxy.sh <虚拟机名称> 6080
```

### Xterm / Serial Console
网页终端控制台依赖：
- `virsh`
- `tmux`

Ubuntu / Debian 可直接补：
```bash
sudo apt-get install -y tmux libvirt-clients
```

新建 VM 的 XML 已自动附带 serial/console 设备；旧 VM 若页面提示未检测到 serial，可重新 define 或重建该实例。

## 5. 权限建议
- 运行 Web 的用户需要有 libvirt 访问权限
- 典型做法：加入 `libvirt` / `kvm` 组
```bash
sudo usermod -aG libvirt,kvm <你的用户>
```
重新登录会话后生效。
## 6. 页面结构与运维建议
- 当前 Dashboard 已拆为：总览 / 网络 / 模板 / 虚拟机 / 镜像 / 系统配置
- 网络页默认通过 bridge 探测下拉 + 内联地址池配置完成日常操作
- 对已被引用的网络、模板、VM，系统会限制危险更新，避免直接把现有对象改炸

## 7. 生产建议
- 用 Nginx / Apache 反向代理 PHP
- 开启 HTTPS
- 修改默认 admin 密码
- 使用 MySQL / MariaDB
- 限制管理入口仅内网可达
- 定期备份数据库和镜像目录

## 8. 最小自检命令
```bash
php -l public/index.php
find src views public -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
php -S 127.0.0.1:8080 -t public
```

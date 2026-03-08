#!/usr/bin/env bash
set -euo pipefail
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_VERSION="${PHP_VERSION:-8.3}"
echo "[1/6] 安装依赖"
sudo apt-get update
sudo apt-get install -y \
  php-cli php-sqlite3 php-mysql php${PHP_VERSION}-libvirt-php \
  qemu-utils qemu-system-x86 libvirt-clients libvirt-daemon-system \
  bridge-utils virtinst cloud-image-utils sqlite3 mariadb-server novnc websockify
if ! php -m | grep -qi '^libvirt$'; then
  echo 'extension=libvirt-php.so' | sudo tee /etc/php/${PHP_VERSION}/mods-available/libvirt.ini >/dev/null
  sudo phpenmod libvirt
fi
echo "[2/6] 启动 libvirt / mariadb"
sudo systemctl daemon-reload
sudo systemctl enable --now libvirtd mariadb
echo "[3/6] 准备存储目录"
sudo mkdir -p /var/libvirt/images/nbkvm
sudo chmod 755 /var/libvirt/images /var/libvirt/images/nbkvm
echo "[4/6] 初始化数据库"
cd "$APP_DIR"
php bin/init_db.php
echo "[5/6] 提示：如需 MySQL，请先导出 NBKVM_DB_* 环境变量后重新执行 init_db.php"
echo "[6/6] 启动开发 Web"
echo "运行：php -S 0.0.0.0:8080 -t public"

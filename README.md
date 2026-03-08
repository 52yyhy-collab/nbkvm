# NBKVM
NBKVM 是一个基于 PHP 的 KVM / libvirt 控制系统，提供这些能力：
- 上传系统镜像（ISO / QCOW2 / RAW）
- 基于镜像创建系统模板
- 基于系统模板创建虚拟机
- 查看虚拟机状态、启动、关机、强制停止、删除
- 通过 **PHP libvirt 扩展** 与 libvirt 交互，磁盘处理使用 `qemu-img`
> 设计目标：控制面逻辑直接走 **php-libvirt**，不把 `virsh` 当主控制接口；磁盘创建/转换仍依赖 `qemu-img`。
## 技术选型
- PHP 8.1+
- SQLite（元数据存储）
- 原生 PHP MVC 风格结构
- libvirt 控制：`php-libvirt`
- 磁盘处理：`qemu-img`
## 功能
### 1. 镜像管理
- 上传镜像文件到 `storage/uploads/`
- 记录镜像元数据
- 校验文件名、大小、类型
### 2. 模板管理
- 从已上传镜像创建模板
- 模板定义 CPU、内存、磁盘总线、网络、OS Variant、cloud-init 可选项
- 支持模板磁盘基础镜像指向已上传的 `qcow2/raw` 文件
- ISO 模板可用于 unattended/manual 安装流程
### 3. 虚拟机管理
- 基于模板创建 VM
- 为 VM 生成磁盘文件到 `storage/vms/<name>/`
- 自动生成 libvirt domain XML
- 可执行启动、关机、强制断电、删除
- 支持通过 `libvirt_domain_get_info()` 获取虚拟机状态
## 目录结构
```text
nbkvm/
├── bin/
│   └── init_db.php
├── config/
│   └── app.php
├── public/
│   ├── assets/
│   └── index.php
├── src/
│   ├── Repositories/
│   ├── Services/
│   └── Support/
├── storage/
│   ├── database/
│   ├── logs/
│   ├── templates/
│   ├── uploads/
│   └── vms/
└── views/
```
## 环境要求
- Linux 主机
- libvirt / KVM 用户态工具
- PHP 8.1+
- **php-libvirt 扩展**
- 对 libvirt 有操作权限的系统账户
推荐安装：
```bash
sudo apt-get install -y php-cli php-sqlite3 qemu-utils libvirt-clients
# php-libvirt 需要按你的发行版/源码方式安装并启用
```
如果要跑内置服务器：
```bash
php -S 0.0.0.0:8080 -t public
```
## 初始化
```bash
php bin/init_db.php
```
初始化后会生成：
- `storage/database/nbkvm.sqlite`
## 配置
编辑 `config/app.php`：
- libvirt URI
- 默认存储目录
- 默认网络桥/网络名
- 命令路径
- 上传大小限制
## 安全说明
这个项目默认面向**受信任的内网管理环境**，当前版本**没有完整用户认证**。同时要求 Web 运行用户具备访问 libvirt 的权限，并已安装启用 php-libvirt。生产使用前建议至少补这些：
- 登录认证
- CSRF 防护
- 审计日志
- 更严格的文件 MIME 校验
- 命令执行账户隔离
- 反向代理 + HTTPS
## 创建 VM 的默认流程
1. 上传镜像
2. 新建模板，绑定镜像
3. 填写 VM 名称 / CPU / 内存 / 磁盘大小 / 网络
4. 系统生成磁盘文件
5. 生成并定义 libvirt domain
6. 可选：立即启动
## 当前限制
- 不直接提供 VNC/noVNC 页面
- ISO 安装模板与 cloud 镜像模板共用一套模型，但部署路径不同
- 没做高级网络管理（bridge / ovs / SR-IOV）
- 没做快照 UI
## 后续建议
- 增加认证与 RBAC
- 增加存储池管理
- 增加快照与克隆
- 集成 noVNC
- 支持 cloud-init 用户数据模板
## GitHub
仓库会按项目名发布为：`nbkvm`

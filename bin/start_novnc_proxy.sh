#!/usr/bin/env bash
set -euo pipefail
if [ $# -lt 1 ]; then
  echo "用法：$0 <虚拟机名称> [监听端口]"
  exit 1
fi
VM_NAME="$1"
LISTEN_PORT="${2:-6080}"
URI="${NBKVM_LIBVIRT_URI:-qemu:///system}"
DISPLAY=$(virsh -c "$URI" vncdisplay "$VM_NAME" | tr -d '[:space:]')
if [ -z "$DISPLAY" ]; then
  echo "未获取到 VNC display，虚拟机可能未启动。"
  exit 1
fi
DISPLAY_NUM="${DISPLAY#:}"
TARGET_PORT=$((5900 + DISPLAY_NUM))
NOVNC_WEB="${NBKVM_NOVNC_WEB:-/usr/share/novnc}"
echo "启动 noVNC 代理：0.0.0.0:${LISTEN_PORT} -> 127.0.0.1:${TARGET_PORT}"
exec websockify --web "$NOVNC_WEB" "$LISTEN_PORT" "127.0.0.1:${TARGET_PORT}"

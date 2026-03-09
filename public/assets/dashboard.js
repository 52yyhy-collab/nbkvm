(() => {
  const dataNode = document.getElementById('dashboard-data');
  if (!dataNode) return;

  let data = {};
  try {
    data = JSON.parse(dataNode.textContent || '{}');
  } catch (error) {
    console.error('Failed to parse dashboard data', error);
    return;
  }

  const networks = Array.isArray(data.networks) ? data.networks : [];
  const templates = Array.isArray(data.templates) ? data.templates : [];
  const vms = Array.isArray(data.vms) ? data.vms : [];
  const templateMap = Object.fromEntries(templates.map((item) => [String(item.id), item]));
  const vmMap = Object.fromEntries(vms.map((item) => [String(item.id), item]));
  const modelOptions = ['virtio', 'e1000', 'rtl8139', 'vmxnet3'];
  const diskBusOptions = ['virtio', 'sata', 'scsi', 'ide'];
  const diskFormatOptions = ['qcow2', 'raw'];
  const diskCacheOptions = ['default', 'none', 'writethrough', 'writeback', 'directsync', 'unsafe'];
  const diskDiscardOptions = ['ignore', 'on', 'unmap'];
  const bridgeCandidates = Array.isArray(data.bridgeCandidates?.bridges) ? data.bridgeCandidates.bridges : [];
  const hostResources = Array.isArray(data.bridgeCandidates?.all) ? data.bridgeCandidates.all : [];
  const hostResourceMap = Object.fromEntries(hostResources.map((item) => [String(item.name), item]));
  const nodeNetworkResources = Array.isArray(data.nodeNetworkResources) ? data.nodeNetworkResources : [];
  const nodeNetworkCapabilities = data.nodeNetworkCapabilities && typeof data.nodeNetworkCapabilities === 'object' ? data.nodeNetworkCapabilities : {};
  const preferredBridgeName = String(data.bridgeCandidates?.preferred_bridge || '');

  const preferredNetwork = () => networks.find((item) => String(item.bridge_name || '') === preferredBridgeName)
    || networks.find((item) => item.name === 'default')
    || networks[0]
    || {
      id: '',
      name: 'default',
      bridge_name: preferredBridgeName || 'vmbr0',
      libvirt_managed: 0,
      ipv4_pool: null,
      ipv6_pool: null,
    };

  const findNetworkById = (networkId) => networks.find((item) => String(item.id) === String(networkId)) || null;
  const findNetworkByName = (name) => networks.find((item) => String(item.name) === String(name)) || null;
  const findNetwork = (networkId) => findNetworkById(networkId) || preferredNetwork();
  const findBridgeResource = (name) => hostResourceMap[String(name)] || null;
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch]));
  const normalizeDatetimeLocal = (value) => {
    const text = String(value || '').trim();
    if (!text) return '';
    return text.replace(' ', 'T').slice(0, 16);
  };

  const nodeBridgeResources = nodeNetworkResources.filter((item) => String(item.type || '').toLowerCase() === 'bridge');
  const nodeBridgeMap = Object.fromEntries(nodeBridgeResources.map((item) => [String(item.name), item]));
  const findNodeBridgeResource = (name) => nodeBridgeMap[String(name)] || null;
  const compatNetworkByBridge = (bridge) => networks.find((item) => String(item.bridge_name || item.name || '') === String(bridge)) || null;
  const compatNetworkForBinding = (binding) => binding?.network || compatNetworkByBridge(binding?.bridge_name || '') || null;

  function makeRawBridgeBinding(bridgeName, networkName = '') {
    const fallback = preferredNetwork();
    const bridge = String(bridgeName || '').trim() || String(fallback.bridge_name || 'vmbr0');
    const compat = compatNetworkByBridge(bridge);
    return {
      key: `bridge:${bridge}`,
      kind: 'bridge',
      bridge_name: bridge,
      network: compat,
      network_id: '',
      network_name: String(networkName || bridge),
      source_type: 'bridge',
      source_name: bridge,
      resource: findBridgeResource(bridge),
      node_resource: findNodeBridgeResource(bridge),
    };
  }

  function makeLibvirtNetworkBinding(network) {
    const bridge = String(network?.bridge_name || network?.name || '').trim();
    return {
      key: `network:${network.id}`,
      kind: 'libvirt-network',
      bridge_name: bridge,
      network,
      network_id: network.id,
      network_name: String(network.name || bridge),
      source_type: 'network',
      source_name: String(network.name || bridge),
      resource: findBridgeResource(bridge),
      node_resource: findNodeBridgeResource(bridge),
    };
  }

  function bridgeBindings() {
    const options = [];
    const seen = new Set();

    [...nodeBridgeResources]
      .sort((left, right) => {
        const leftName = String(left.name || '');
        const rightName = String(right.name || '');
        const leftWeight = leftName === preferredBridgeName ? 0 : (leftName.startsWith('vmbr') ? 1 : 2);
        const rightWeight = rightName === preferredBridgeName ? 0 : (rightName.startsWith('vmbr') ? 1 : 2);
        if (leftWeight !== rightWeight) return leftWeight - rightWeight;
        return leftName.localeCompare(rightName);
      })
      .forEach((resource) => {
        const bridge = String(resource.name || '').trim();
        if (!bridge || seen.has(`bridge:${bridge}`)) return;
        options.push(makeRawBridgeBinding(bridge, bridge));
        seen.add(`bridge:${bridge}`);
      });

    bridgeCandidates.forEach((resource) => {
      const bridge = String(resource.name || '').trim();
      if (!bridge || seen.has(`bridge:${bridge}`)) return;
      options.push(makeRawBridgeBinding(bridge));
      seen.add(`bridge:${bridge}`);
    });

    networks
      .filter((network) => Number(network.libvirt_managed || 0) === 1)
      .sort((left, right) => String(left.name || '').localeCompare(String(right.name || '')))
      .forEach((network) => {
        const key = `network:${network.id}`;
        if (seen.has(key)) return;
        options.push(makeLibvirtNetworkBinding(network));
        seen.add(key);
      });

    if (!options.length) {
      options.push(makeRawBridgeBinding(preferredBridgeName || preferredNetwork().bridge_name || 'vmbr0'));
    }

    return options;
  }

  function preferredBinding() {
    const options = bridgeBindings();
    return options.find((item) => item.kind !== 'libvirt-network' && item.bridge_name === preferredBridgeName)
      || options.find((item) => item.kind !== 'libvirt-network' && String(item.bridge_name || '').startsWith('vmbr'))
      || options.find((item) => item.kind === 'libvirt-network' && String(item.network_name || '') === 'default')
      || options[0]
      || makeRawBridgeBinding(preferredBridgeName || 'vmbr0');
  }

  function resolveBinding(valueOrNic) {
    const options = bridgeBindings();
    const fromKey = (key) => {
      const value = String(key || '').trim();
      if (!value) return null;
      const hit = options.find((item) => item.key === value);
      if (hit) return hit;
      if (value.startsWith('bridge:')) return makeRawBridgeBinding(value.slice(7));
      if (value.startsWith('network:')) {
        const network = findNetworkById(value.slice(8));
        if (network) return makeLibvirtNetworkBinding(network);
      }
      return null;
    };

    if (typeof valueOrNic === 'string') {
      return fromKey(valueOrNic) || preferredBinding();
    }

    if (valueOrNic && typeof valueOrNic === 'object') {
      const networkId = String(valueOrNic.network_id ?? valueOrNic.id ?? '').trim();
      if (networkId && networkId !== '0') {
        return fromKey(`network:${networkId}`) || preferredBinding();
      }

      const bridge = String(valueOrNic.bridge || valueOrNic.bridge_name || '').trim();
      const networkName = String(valueOrNic.network_name || valueOrNic.name || '').trim();
      if (bridge) {
        const bridgeHit = options.find((item) => item.kind !== 'libvirt-network' && item.bridge_name === bridge);
        if (bridgeHit) return bridgeHit;
        return makeRawBridgeBinding(bridge, networkName);
      }

      if (networkName) {
        const network = findNetworkByName(networkName);
        if (network && Number(network.libvirt_managed || 0) === 1) {
          return fromKey(`network:${network.id}`) || preferredBinding();
        }
        if (findNodeBridgeResource(networkName) || findBridgeResource(networkName)) {
          return makeRawBridgeBinding(networkName, networkName);
        }
      }
    }

    return preferredBinding();
  }

  function bindingLabel(binding) {
    if (binding.kind === 'libvirt-network') {
      return `${binding.network_name || binding.source_name || '-'} / managed libvirt network / ${binding.bridge_name || '-'}`;
    }
    const parts = [binding.bridge_name || '-'];
    if (binding.node_resource) {
      parts.push('Linux Bridge');
      if (binding.node_resource.cidr) parts.push(String(binding.node_resource.cidr));
    } else if (binding.resource) {
      parts.push('Host Bridge');
    } else {
      parts.push('Bridge');
    }
    return parts.join(' / ');
  }

  function bindingOptionsHtml(selectedKey) {
    const options = bridgeBindings();
    if (!options.some((binding) => String(binding.key) === String(selectedKey))) {
      options.unshift(resolveBinding(selectedKey));
    }
    return options.map((binding) => `<option value="${escapeHtml(binding.key)}" ${String(binding.key) === String(selectedKey) ? 'selected' : ''}>${escapeHtml(bindingLabel(binding))}</option>`).join('');
  }

  function defaultNic(bindingOrNetwork = preferredBinding()) {
    const binding = resolveBinding(bindingOrNetwork);
    const compatNetwork = compatNetworkForBinding(binding);
    return {
      network_id: binding.network_id || '',
      network_name: binding.network_name || binding.bridge_name || '',
      bridge: binding.bridge_name || '',
      source_type: binding.source_type || 'bridge',
      source_name: binding.source_name || binding.bridge_name || '',
      model: 'virtio',
      vlan_tag: '',
      mac: '',
      firewall: false,
      link_down: false,
      ipv4_mode: 'dhcp',
      ipv4_address: '',
      ipv4_prefix_length: '',
      ipv4_gateway: '',
      ipv4_dns_servers: '',
      ipv6_mode: compatNetwork?.ipv6_pool || compatNetwork?.ipv6_cidr || binding.node_resource?.ipv6_cidr ? 'auto' : 'none',
      ipv6_address: '',
      ipv6_prefix_length: '',
      ipv6_gateway: '',
      ipv6_dns_servers: '',
    };
  }

  function normalizeNic(nic = {}) {
    const binding = resolveBinding(nic);
    const item = {...defaultNic(binding), ...nic};
    if (!String(item.network_name || '').trim()) item.network_name = binding.network_name || binding.bridge_name || '';
    if (!String(item.bridge || '').trim()) item.bridge = binding.bridge_name || '';
    if (!String(item.source_type || '').trim()) item.source_type = binding.source_type || 'bridge';
    if (!String(item.source_name || '').trim()) item.source_name = binding.source_name || binding.bridge_name || '';
    if (!String(item.network_id || '').trim() && binding.network_id) item.network_id = binding.network_id;
    return item;
  }

  function primaryNetworkNameForNic(nic = {}) {
    const binding = resolveBinding(nic);
    return String(nic.network_name || binding.network_name || binding.bridge_name || preferredNetwork().name || 'default');
  }

  function nicPoolHint(binding, family) {
    const network = compatNetworkForBinding(binding);
    if (!network) {
      return family === 'ipv6'
        ? '当前 Bridge 没有关联默认 IPv6 池；如需 pool，可在兼容层补充。'
        : '当前 Bridge 没有关联默认 IPv4 池；如需 pool，可在兼容层补充。';
    }
    const pool = family === 'ipv6' ? network.ipv6_pool : network.ipv4_pool;
    if (!pool) {
      return family === 'ipv6' ? '兼容层未配置 IPv6 默认池。' : '兼容层未配置 IPv4 默认池。';
    }
    return `${pool.start_ip} - ${pool.end_ip}${pool.dns_servers ? ` / DNS ${pool.dns_servers}` : ''}`;
  }

  function bindingHint(binding) {
    const resource = binding.resource || findBridgeResource(binding.bridge_name);
    const compat = compatNetworkForBinding(binding);
    const parts = [`bridge=${binding.bridge_name || '-'}`];
    parts.push(binding.kind === 'libvirt-network' ? `source=network:${binding.network_name || binding.source_name || '-'}` : 'source=bridge');
    if (binding.node_resource?.cidr) parts.push(`IPv4 ${binding.node_resource.cidr}`);
    else if (compat?.cidr) parts.push(`IPv4 ${compat.cidr}`);
    if (binding.node_resource?.ipv6_cidr) parts.push(`IPv6 ${binding.node_resource.ipv6_cidr}`);
    else if (compat?.ipv6_cidr) parts.push(`IPv6 ${compat.ipv6_cidr}`);
    if (resource?.state) parts.push(`state ${resource.state}`);
    if (resource?.mtu) parts.push(`mtu ${resource.mtu}`);
    if (Array.isArray(resource?.ports) && resource.ports.length) parts.push(`ports ${resource.ports.join(', ')}`);
    return parts.join(' / ');
  }

  const modelOptionsHtml = (selected) => modelOptions.map((model) => `<option value="${model}" ${String(model) === String(selected) ? 'selected' : ''}>${model}</option>`).join('');
  const modeOptionsHtml = (options, selected) => options.map((value) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${value}</option>`).join('');
  const diskBusOptionsHtml = (selected) => diskBusOptions.map((value) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${value}</option>`).join('');
  const diskFormatOptionsHtml = (selected) => diskFormatOptions.map((value) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${value}</option>`).join('');
  const diskCacheOptionsHtml = (selected) => diskCacheOptions.map((value) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${value}</option>`).join('');
  const diskDiscardOptionsHtml = (selected) => diskDiscardOptions.map((value) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${value}</option>`).join('');
  const ipv4ModeOptionsHtml = (selected) => [
    ['dhcp', 'dhcp'],
    ['static', 'static'],
    ['pool', 'pool（高级兼容）'],
    ['none', 'none'],
  ].map(([value, label]) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${label}</option>`).join('');
  const ipv6ModeOptionsHtml = (selected) => [
    ['auto', 'auto'],
    ['static', 'static'],
    ['pool', 'pool（高级兼容）'],
    ['none', 'none'],
  ].map(([value, label]) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${label}</option>`).join('');

  function netPreviewText(binding, item) {
    const parts = [`${item.model || 'virtio'}`];
    if (item.mac) parts.push(`macaddr=${item.mac}`);
    parts.push(`bridge=${binding.bridge_name || '-'}`);
    if (item.vlan_tag) parts.push(`tag=${item.vlan_tag}`);
    parts.push(`firewall=${item.firewall ? 1 : 0}`);
    parts.push(`link_down=${item.link_down ? 1 : 0}`);
    return parts.join(',');
  }

  function ipconfigPreviewText(item) {
    const ipv4 = (() => {
      if (item.ipv4_mode === 'static') {
        const base = item.ipv4_address && item.ipv4_prefix_length ? `${item.ipv4_address}/${item.ipv4_prefix_length}` : 'manual';
        return `ip=${base}${item.ipv4_gateway ? `,gw=${item.ipv4_gateway}` : ''}`;
      }
      if (item.ipv4_mode === 'pool') return 'ip=pool';
      if (item.ipv4_mode === 'none') return 'ip=none';
      return 'ip=dhcp';
    })();
    const ipv6 = (() => {
      if (item.ipv6_mode === 'static') {
        const base = item.ipv6_address && item.ipv6_prefix_length ? `${item.ipv6_address}/${item.ipv6_prefix_length}` : 'manual';
        return `ip6=${base}${item.ipv6_gateway ? `,gw6=${item.ipv6_gateway}` : ''}`;
      }
      if (item.ipv6_mode === 'pool') return 'ip6=pool';
      if (item.ipv6_mode === 'none') return 'ip6=none';
      return 'ip6=auto';
    })();
    return `${ipv4}, ${ipv6}`;
  }

  function renderNicCard(nic, index) {
    const item = normalizeNic(nic);
    const binding = resolveBinding(item);
    return `
      <div class="nic-card" data-index="${index}">
        <div class="editor-header">
          <div>
            <strong>net${index}</strong>
            <div class="muted editor-kicker">ipconfig${index}</div>
          </div>
          <button class="btn danger js-remove-nic" type="button">删除</button>
        </div>
        <div class="field-note nic-pve-net-preview">${escapeHtml(netPreviewText(binding, item))}</div>
        <div class="field-note nic-pve-ip-preview">${escapeHtml(ipconfigPreviewText(item))}</div>
        <div class="row-3">
          <div>
            <label>Bridge</label>
            <select data-field="bridge_binding">${bindingOptionsHtml(binding.key)}</select>
            <div class="muted nic-network-hint"></div>
          </div>
          <div><label>Model</label><select data-field="model">${modelOptionsHtml(item.model || 'virtio')}</select></div>
          <div><label>VLAN Tag</label><input type="number" min="1" max="4094" data-field="vlan_tag" value="${escapeHtml(item.vlan_tag || '')}" placeholder="留空表示不打 tag"></div>
        </div>
        <div class="row-3 compact-fields">
          <div><label>MAC</label><input type="text" data-field="mac" value="${escapeHtml(item.mac || '')}" placeholder="52:54:00:12:34:56"></div>
          <div><label><input class="inline" type="checkbox" data-field="firewall" ${item.firewall ? 'checked' : ''}> firewall</label></div>
          <div><label><input class="inline" type="checkbox" data-field="link_down" ${item.link_down ? 'checked' : ''}> link_down</label></div>
        </div>
        <div class="nic-ip-block">
          <div class="muted editor-kicker">ipconfig${index} / IPv4</div>
          <div class="row-3">
            <div><label>IPv4 模式</label><select data-field="ipv4_mode">${ipv4ModeOptionsHtml(item.ipv4_mode || 'dhcp')}</select></div>
            <div class="nic-mode-panel" data-mode-panel="ipv4-pool"><label>IPv4 默认池（高级兼容）</label><div class="field-note nic-ipv4-pool-hint">${escapeHtml(nicPoolHint(binding, 'ipv4'))}</div></div>
            <div class="nic-mode-panel" data-mode-panel="ipv4-dhcp"><label>DHCP</label><div class="field-note">按 PVE 思路由所选 Bridge 所在网络环境 / 来宾系统获取 IPv4。</div></div>
          </div>
          <div class="row-3 nic-static-fields" data-mode-panel="ipv4-static">
            <div><label>IPv4 地址</label><input type="text" data-field="ipv4_address" value="${escapeHtml(item.ipv4_address || '')}" placeholder="10.0.10.20"></div>
            <div><label>IPv4 前缀</label><input type="number" min="1" max="32" data-field="ipv4_prefix_length" value="${escapeHtml(item.ipv4_prefix_length || '')}" placeholder="24"></div>
            <div><label>IPv4 网关</label><input type="text" data-field="ipv4_gateway" value="${escapeHtml(item.ipv4_gateway || '')}" placeholder="10.0.10.1"></div>
          </div>
          <div class="row-1 nic-static-fields" data-mode-panel="ipv4-static-dns">
            <div><label>IPv4 DNS</label><input type="text" data-field="ipv4_dns_servers" value="${escapeHtml(item.ipv4_dns_servers || '')}" placeholder="1.1.1.1,8.8.8.8"></div>
          </div>
        </div>
        <div class="nic-ip-block">
          <div class="muted editor-kicker">ipconfig${index} / IPv6</div>
          <div class="row-3">
            <div><label>IPv6 模式</label><select data-field="ipv6_mode">${ipv6ModeOptionsHtml(item.ipv6_mode || 'none')}</select></div>
            <div class="nic-mode-panel" data-mode-panel="ipv6-pool"><label>IPv6 默认池（高级兼容）</label><div class="field-note nic-ipv6-pool-hint">${escapeHtml(nicPoolHint(binding, 'ipv6'))}</div></div>
            <div class="nic-mode-panel" data-mode-panel="ipv6-auto"><label>IPv6 自动配置</label><div class="field-note">按 ipconfigX 心智由来宾系统 / RA / cloud-init 自动配置。</div></div>
          </div>
          <div class="row-3 nic-static-fields" data-mode-panel="ipv6-static">
            <div><label>IPv6 地址</label><input type="text" data-field="ipv6_address" value="${escapeHtml(item.ipv6_address || '')}" placeholder="fd00:10::20"></div>
            <div><label>IPv6 前缀</label><input type="number" min="1" max="128" data-field="ipv6_prefix_length" value="${escapeHtml(item.ipv6_prefix_length || '')}" placeholder="64"></div>
            <div><label>IPv6 网关</label><input type="text" data-field="ipv6_gateway" value="${escapeHtml(item.ipv6_gateway || '')}" placeholder="fd00:10::1"></div>
          </div>
          <div class="row-1 nic-static-fields" data-mode-panel="ipv6-static-dns">
            <div><label>IPv6 DNS</label><input type="text" data-field="ipv6_dns_servers" value="${escapeHtml(item.ipv6_dns_servers || '')}" placeholder="2606:4700:4700::1111"></div>
          </div>
        </div>
      </div>`;
  }

  function collectEditorNics(container) {
    return Array.from(container.querySelectorAll('.nic-card')).map((card) => {
      const binding = resolveBinding(card.querySelector('[data-field="bridge_binding"]').value || preferredBinding().key);
      return {
        network_id: binding.network_id || '',
        network_name: binding.network_name || binding.bridge_name || '',
        bridge: binding.bridge_name || '',
        source_type: binding.source_type || 'bridge',
        source_name: binding.source_name || binding.bridge_name || '',
        model: card.querySelector('[data-field="model"]').value || 'virtio',
        vlan_tag: card.querySelector('[data-field="vlan_tag"]').value || '',
        mac: card.querySelector('[data-field="mac"]').value.trim(),
        firewall: card.querySelector('[data-field="firewall"]').checked,
        link_down: card.querySelector('[data-field="link_down"]').checked,
        ipv4_mode: card.querySelector('[data-field="ipv4_mode"]').value || 'dhcp',
        ipv4_address: card.querySelector('[data-field="ipv4_address"]').value.trim(),
        ipv4_prefix_length: card.querySelector('[data-field="ipv4_prefix_length"]').value || '',
        ipv4_gateway: card.querySelector('[data-field="ipv4_gateway"]').value.trim(),
        ipv4_dns_servers: card.querySelector('[data-field="ipv4_dns_servers"]').value.trim(),
        ipv6_mode: card.querySelector('[data-field="ipv6_mode"]').value || 'none',
        ipv6_address: card.querySelector('[data-field="ipv6_address"]').value.trim(),
        ipv6_prefix_length: card.querySelector('[data-field="ipv6_prefix_length"]').value || '',
        ipv6_gateway: card.querySelector('[data-field="ipv6_gateway"]').value.trim(),
        ipv6_dns_servers: card.querySelector('[data-field="ipv6_dns_servers"]').value.trim(),
      };
    });
  }

  function syncNicCard(card) {
    const binding = resolveBinding(card.querySelector('[data-field="bridge_binding"]').value || preferredBinding().key);
    const hint = card.querySelector('.nic-network-hint');
    if (hint) {
      hint.textContent = bindingHint(binding);
    }

    const compatNetwork = compatNetworkForBinding(binding);
    if (!compatNetwork) {
      const ipv4ModeNode = card.querySelector('[data-field="ipv4_mode"]');
      const ipv6ModeNode = card.querySelector('[data-field="ipv6_mode"]');
      if (ipv4ModeNode && ipv4ModeNode.value === 'pool') ipv4ModeNode.value = 'dhcp';
      if (ipv6ModeNode && ipv6ModeNode.value === 'pool') ipv6ModeNode.value = 'none';
    }

    const ipv4PoolHint = card.querySelector('.nic-ipv4-pool-hint');
    const ipv6PoolHint = card.querySelector('.nic-ipv6-pool-hint');
    if (ipv4PoolHint) ipv4PoolHint.textContent = nicPoolHint(binding, 'ipv4');
    if (ipv6PoolHint) ipv6PoolHint.textContent = nicPoolHint(binding, 'ipv6');

    const item = {
      model: card.querySelector('[data-field="model"]').value || 'virtio',
      mac: card.querySelector('[data-field="mac"]').value.trim(),
      vlan_tag: card.querySelector('[data-field="vlan_tag"]').value || '',
      firewall: card.querySelector('[data-field="firewall"]').checked,
      link_down: card.querySelector('[data-field="link_down"]').checked,
      ipv4_mode: card.querySelector('[data-field="ipv4_mode"]').value || 'dhcp',
      ipv4_address: card.querySelector('[data-field="ipv4_address"]').value.trim(),
      ipv4_prefix_length: card.querySelector('[data-field="ipv4_prefix_length"]').value || '',
      ipv4_gateway: card.querySelector('[data-field="ipv4_gateway"]').value.trim(),
      ipv6_mode: card.querySelector('[data-field="ipv6_mode"]').value || 'none',
      ipv6_address: card.querySelector('[data-field="ipv6_address"]').value.trim(),
      ipv6_prefix_length: card.querySelector('[data-field="ipv6_prefix_length"]').value || '',
      ipv6_gateway: card.querySelector('[data-field="ipv6_gateway"]').value.trim(),
    };
    const netPreview = card.querySelector('.nic-pve-net-preview');
    const ipPreview = card.querySelector('.nic-pve-ip-preview');
    if (netPreview) netPreview.textContent = netPreviewText(binding, item);
    if (ipPreview) ipPreview.textContent = ipconfigPreviewText(item);

    const ipv4Mode = item.ipv4_mode;
    const ipv6Mode = item.ipv6_mode;
    const toggle = (selector, visible) => card.querySelectorAll(selector).forEach((node) => { node.style.display = visible ? '' : 'none'; });
    toggle('[data-mode-panel="ipv4-static"]', ipv4Mode === 'static');
    toggle('[data-mode-panel="ipv4-static-dns"]', ipv4Mode === 'static');
    toggle('[data-mode-panel="ipv4-pool"]', ipv4Mode === 'pool');
    toggle('[data-mode-panel="ipv4-dhcp"]', ipv4Mode === 'dhcp');
    toggle('[data-mode-panel="ipv6-static"]', ipv6Mode === 'static');
    toggle('[data-mode-panel="ipv6-static-dns"]', ipv6Mode === 'static');
    toggle('[data-mode-panel="ipv6-pool"]', ipv6Mode === 'pool');
    toggle('[data-mode-panel="ipv6-auto"]', ipv6Mode === 'auto');
  }

  function setNicEditorReadOnly(container, readOnly) {
    const locked = Boolean(readOnly);
    container.classList.toggle('readonly', locked);
    container.querySelectorAll('input,select,button').forEach((node) => {
      node.disabled = locked;
    });
  }

  function reindex(container, selector, prefix) {
    Array.from(container.querySelectorAll(selector)).forEach((card, index) => {
      card.dataset.index = index;
      const title = card.querySelector('.editor-header strong');
      if (title) title.textContent = `${prefix}${index}`;
      const kickers = card.querySelectorAll('.editor-kicker');
      kickers.forEach((node) => {
        node.textContent = String(node.textContent || '').replace(/^ipconfig\d+/, `ipconfig${index}`);
      });
    });
  }

  function wireNicEditor(container, hiddenInput, preview, options = {}) {
    container._ensurePreview = () => {
      const nics = collectEditorNics(container);
      hiddenInput.value = JSON.stringify(nics);
      if (preview) preview.value = JSON.stringify(nics, null, 2);
      if (options.onChange) options.onChange(nics);
    };
    container._setReadOnly = (readOnly) => setNicEditorReadOnly(container, readOnly);

    Array.from(container.querySelectorAll('.nic-card')).forEach((card) => {
      card.querySelectorAll('input,select').forEach((node) => {
        const refresh = () => {
          if (node.dataset.field === 'bridge_binding' || node.dataset.field === 'ipv4_mode' || node.dataset.field === 'ipv6_mode') {
            syncNicCard(card);
          }
          container._ensurePreview();
        };
        node.addEventListener('change', refresh);
        node.addEventListener('input', container._ensurePreview);
      });
      const removeBtn = card.querySelector('.js-remove-nic');
      if (removeBtn) {
        removeBtn.addEventListener('click', () => {
          card.remove();
          if (!container.querySelector('.nic-card')) {
            addNic(container, hiddenInput, preview, defaultNic(preferredBinding()), options);
            return;
          }
          reindex(container, '.nic-card', 'net');
          container._ensurePreview();
        });
      }
      syncNicCard(card);
    });

    setNicEditorReadOnly(container, Boolean(options.readOnly));
    container._ensurePreview();
  }

  function renderNicEditor(container, hiddenInput, preview, nics, options = {}) {
    const list = Array.isArray(nics) && nics.length ? nics.map(normalizeNic) : [defaultNic(preferredBinding())];
    container.innerHTML = list.map((nic, index) => renderNicCard(nic, index)).join('');
    wireNicEditor(container, hiddenInput, preview, options);
  }

  function addNic(container, hiddenInput, preview, nic, options = {}) {
    if (options.readOnly) return;
    const nextIndex = container.querySelectorAll('.nic-card').length;
    container.insertAdjacentHTML('beforeend', renderNicCard(normalizeNic(nic), nextIndex));
    wireNicEditor(container, hiddenInput, preview, options);
    reindex(container, '.nic-card', 'net');
  }

  function defaultDisk(index = 0) {
    return {
      name: `disk${index}`,
      size_gb: index === 0 ? 20 : 10,
      bus: 'virtio',
      format: 'qcow2',
      storage: '',
      ssd_emulation: false,
      discard: 'ignore',
      cache: 'default',
      is_primary: index === 0,
    };
  }

  function normalizeDisk(disk = {}, index = 0) {
    return {...defaultDisk(index), ...disk, is_primary: Boolean(disk.is_primary ?? index === 0)};
  }

  function renderDiskCard(disk, index) {
    const item = normalizeDisk(disk, index);
    return `
      <div class="disk-card" data-index="${index}">
        <div class="editor-header">
          <strong>disk${index}</strong>
          <button class="btn danger js-remove-disk" type="button">删除</button>
        </div>
        <div class="row-4 compact-fields">
          <div><label>名称</label><input type="text" data-field="name" value="${escapeHtml(item.name)}"></div>
          <div><label>容量(GB)</label><input type="number" min="1" data-field="size_gb" value="${escapeHtml(item.size_gb)}"></div>
          <div><label>Bus</label><select data-field="bus">${diskBusOptionsHtml(item.bus)}</select></div>
          <div><label>Format</label><select data-field="format">${diskFormatOptionsHtml(item.format)}</select></div>
        </div>
        <div class="row-4 compact-fields">
          <div><label>Storage</label><input type="text" data-field="storage" value="${escapeHtml(item.storage || '')}" placeholder="local-lvm / nfs-ssd"></div>
          <div><label>Cache</label><select data-field="cache">${diskCacheOptionsHtml(item.cache || 'default')}</select></div>
          <div><label>Discard</label><select data-field="discard">${diskDiscardOptionsHtml(item.discard || 'ignore')}</select></div>
          <div><label><input class="inline" type="checkbox" data-field="ssd_emulation" ${item.ssd_emulation ? 'checked' : ''}> SSD emulate</label></div>
        </div>
        <label><input class="inline" type="checkbox" data-field="is_primary" ${item.is_primary ? 'checked' : ''}> 设为主盘</label>
      </div>`;
  }

  function collectDisks(container) {
    return Array.from(container.querySelectorAll('.disk-card')).map((card) => ({
      name: card.querySelector('[data-field="name"]').value.trim(),
      size_gb: Number(card.querySelector('[data-field="size_gb"]').value || 1),
      bus: card.querySelector('[data-field="bus"]').value || 'virtio',
      format: card.querySelector('[data-field="format"]').value || 'qcow2',
      storage: card.querySelector('[data-field="storage"]').value.trim(),
      cache: card.querySelector('[data-field="cache"]').value || 'default',
      discard: card.querySelector('[data-field="discard"]').value || 'ignore',
      ssd_emulation: card.querySelector('[data-field="ssd_emulation"]').checked,
      is_primary: card.querySelector('[data-field="is_primary"]').checked,
    }));
  }

  function enforceSinglePrimary(container, currentCard) {
    container.querySelectorAll('[data-field="is_primary"]').forEach((checkbox) => {
      if (checkbox !== currentCard.querySelector('[data-field="is_primary"]')) {
        checkbox.checked = false;
      }
    });
  }

  function wireDiskEditor(container, hiddenInput, preview) {
    container._ensurePreview = () => {
      let disks = collectDisks(container);
      if (!disks.some((disk) => disk.is_primary) && disks[0]) disks[0].is_primary = true;
      hiddenInput.value = JSON.stringify(disks);
      if (preview) preview.value = JSON.stringify(disks, null, 2);
    };
    Array.from(container.querySelectorAll('.disk-card')).forEach((card) => {
      card.querySelectorAll('input,select').forEach((node) => {
        const refresh = () => {
          if (node.dataset.field === 'is_primary' && node.checked) {
            enforceSinglePrimary(container, card);
          }
          container._ensurePreview();
        };
        node.addEventListener('change', refresh);
        node.addEventListener('input', container._ensurePreview);
      });
      const removeBtn = card.querySelector('.js-remove-disk');
      if (removeBtn) {
        removeBtn.addEventListener('click', () => {
          card.remove();
          if (!container.querySelector('.disk-card')) {
            addDisk(container, hiddenInput, preview, defaultDisk(0));
            return;
          }
          reindex(container, '.disk-card', 'disk');
          container._ensurePreview();
        });
      }
    });
    container._ensurePreview();
  }

  function renderDiskEditor(container, hiddenInput, preview, disks) {
    const list = Array.isArray(disks) && disks.length ? disks.map((item, index) => normalizeDisk(item, index)) : [defaultDisk(0)];
    container.innerHTML = list.map((disk, index) => renderDiskCard(disk, index)).join('');
    wireDiskEditor(container, hiddenInput, preview);
  }

  function addDisk(container, hiddenInput, preview, disk) {
    const nextIndex = container.querySelectorAll('.disk-card').length;
    container.insertAdjacentHTML('beforeend', renderDiskCard(normalizeDisk(disk, nextIndex), nextIndex));
    wireDiskEditor(container, hiddenInput, preview);
    reindex(container, '.disk-card', 'disk');
  }

  function initNetworkPage() {
    const nodeForm = document.getElementById('node-resource-form');
    if (nodeForm) {
      const typeNode = document.getElementById('node-resource-type');
      const editState = document.getElementById('node-resource-edit-state');
      const portsWrap = document.querySelector('[data-node-field="ports"]');
      const portsLabel = document.getElementById('node-resource-ports-label');
      const bondModeWrap = document.querySelector('[data-node-field="bond-mode"]');
      const parentWrap = document.querySelector('[data-node-field="parent"]');
      const vlanIdWrap = document.querySelector('[data-node-field="vlan-id"]');
      const bridgeVlanAwareWrap = document.querySelector('[data-node-field="bridge-vlan-aware"]');

      const toggleNodeField = (node, visible) => {
        if (!node) return;
        node.style.display = visible ? '' : 'none';
      };

      const syncNodeType = () => {
        const type = String(typeNode?.value || 'bridge');
        toggleNodeField(portsWrap, type !== 'vlan');
        toggleNodeField(bondModeWrap, type === 'bond');
        toggleNodeField(parentWrap, type === 'vlan');
        toggleNodeField(vlanIdWrap, type === 'vlan');
        toggleNodeField(bridgeVlanAwareWrap, type === 'bridge');
        if (portsLabel) {
          portsLabel.textContent = type === 'bond' ? 'Bond Slaves' : 'Bridge Ports';
        }
      };

      const resetNodeForm = () => {
        nodeForm.reset();
        document.getElementById('node-resource-id').value = '';
        document.getElementById('node-resource-name').value = '';
        document.getElementById('node-resource-type').value = 'bridge';
        document.getElementById('node-resource-ports').value = '';
        document.getElementById('node-resource-parent').value = '';
        document.getElementById('node-resource-vlan-id').value = '';
        document.getElementById('node-resource-bond-mode').value = 'active-backup';
        document.getElementById('node-resource-bridge-vlan-aware').checked = false;
        document.getElementById('node-resource-cidr').value = '';
        document.getElementById('node-resource-gateway').value = '';
        document.getElementById('node-resource-ipv6-cidr').value = '';
        document.getElementById('node-resource-ipv6-gateway').value = '';
        document.getElementById('node-resource-mtu').value = '';
        document.getElementById('node-resource-autostart').checked = false;
        document.getElementById('node-resource-comments').value = '';
        editState.classList.add('hidden-block');
        editState.textContent = '';
        syncNodeType();
      };

      const applyNodeResource = (item, label = '编辑节点网络对象') => {
        document.getElementById('node-resource-id').value = item.id || '';
        document.getElementById('node-resource-name').value = item.name || '';
        document.getElementById('node-resource-type').value = item.type || 'bridge';
        document.getElementById('node-resource-ports').value = item.ports_text || '';
        document.getElementById('node-resource-parent').value = item.parent || '';
        document.getElementById('node-resource-vlan-id').value = item.vlan_id || '';
        document.getElementById('node-resource-bond-mode').value = item.bond_mode || 'active-backup';
        document.getElementById('node-resource-bridge-vlan-aware').checked = Number(item.bridge_vlan_aware || 0) === 1;
        document.getElementById('node-resource-cidr').value = item.cidr || '';
        document.getElementById('node-resource-gateway').value = item.gateway || '';
        document.getElementById('node-resource-ipv6-cidr').value = item.ipv6_cidr || '';
        document.getElementById('node-resource-ipv6-gateway').value = item.ipv6_gateway || '';
        document.getElementById('node-resource-mtu').value = item.mtu || '';
        document.getElementById('node-resource-autostart').checked = Number(item.autostart || 0) === 1;
        document.getElementById('node-resource-comments').value = item.comments || '';
        editState.textContent = `${label}：${item.name || ''}`;
        editState.classList.remove('hidden-block');
        syncNodeType();
        nodeForm.scrollIntoView({behavior: 'smooth', block: 'start'});
      };

      document.getElementById('node-resource-form-reset')?.addEventListener('click', resetNodeForm);
      typeNode?.addEventListener('change', syncNodeType);
      document.querySelectorAll('.js-import-node-resource').forEach((button) => {
        button.addEventListener('click', () => {
          const item = JSON.parse(button.dataset.resource || '{}');
          applyNodeResource(item, '导入宿主对象到候选层');
        });
      });
      document.querySelectorAll('.js-edit-node-resource').forEach((button) => {
        button.addEventListener('click', () => {
          const item = JSON.parse(button.dataset.resource || '{}');
          applyNodeResource(item, `编辑节点网络对象 #${item.id || ''}`);
        });
      });
      resetNodeForm();
    }

    const form = document.getElementById('network-form');
    if (!form) return;

    const bridgeSelect = document.getElementById('network-bridge-select');
    const bridgeInput = document.getElementById('network-bridge');
    const customWrap = document.getElementById('network-bridge-custom-wrap');
    const editState = document.getElementById('network-edit-state');
    const managedMode = document.getElementById('network-libvirt-managed');
    const managedPanel = document.getElementById('network-managed-dhcp-panel');
    const resourceSummary = document.getElementById('network-bridge-resource-summary');
    const poolPanel = document.getElementById('network-pool-panel');

    const currentBridgeValue = () => bridgeSelect?.value === '__custom__'
      ? String(bridgeInput?.value || '').trim()
      : String(bridgeSelect?.value || bridgeInput?.value || '').trim();

    const renderBridgeSummary = () => {
      if (!resourceSummary) return;
      const bridge = currentBridgeValue();
      const resource = findBridgeResource(bridge);
      if (!bridge) {
        resourceSummary.textContent = '请选择 Bridge 资源。';
        return;
      }
      if (!resource) {
        resourceSummary.textContent = `当前选择：${bridge} / 宿主尚未探测到该 Bridge，可用于兼容或手工预填。`;
        return;
      }
      const parts = [
        `${resource.name} / ${resource.type_label || resource.type || 'bridge'}`,
        `state ${resource.state || 'unknown'}`,
        `mtu ${resource.mtu || '-'}`,
      ];
      if (Array.isArray(resource.ports) && resource.ports.length) parts.push(`ports ${resource.ports.join(', ')}`);
      if (Array.isArray(resource.addresses) && resource.addresses.length) parts.push(`addr ${resource.addresses.join(' ; ')}`);
      resourceSummary.textContent = parts.join(' / ');
    };

    const syncBridgeInput = () => {
      if (!bridgeSelect || !bridgeInput || !customWrap) return;
      if (bridgeSelect.value === '__custom__') {
        customWrap.classList.remove('hidden-block');
      } else {
        customWrap.classList.add('hidden-block');
        bridgeInput.value = bridgeSelect.value || bridgeInput.value;
      }
      renderBridgeSummary();
    };

    const syncManagedPanels = () => {
      const managed = String(managedMode?.value || '0') === '1';
      if (managedPanel) {
        managedPanel.open = managed;
        managedPanel.classList.toggle('hidden-block', !managed);
      }
    };

    const applyBridgeValue = (value) => {
      if (!bridgeSelect || !bridgeInput) return;
      const option = Array.from(bridgeSelect.options).find((item) => item.value === value);
      if (option) {
        bridgeSelect.value = value;
        bridgeInput.value = value || '';
      } else {
        bridgeSelect.value = '__custom__';
        bridgeInput.value = value || '';
      }
      syncBridgeInput();
    };

    const reset = () => {
      form.reset();
      document.getElementById('network-id').value = '';
      document.getElementById('network-name').value = 'default';
      document.getElementById('network-cidr').value = '192.168.122.0/24';
      document.getElementById('network-gateway').value = '192.168.122.1';
      document.getElementById('network-dhcp-start').value = '192.168.122.2';
      document.getElementById('network-dhcp-end').value = '192.168.122.254';
      document.getElementById('network-ipv4-pool-dns').value = '1.1.1.1,8.8.8.8';
      document.getElementById('network-ipv6-pool-dns').value = '2606:4700:4700::1111';
      editState.classList.add('hidden-block');
      editState.textContent = '';
      managedMode.value = '0';
      if (poolPanel) poolPanel.open = false;
      applyBridgeValue(preferredBridgeName || (bridgeSelect && bridgeSelect.options[0] ? bridgeSelect.options[0].value : 'vmbr0'));
      syncManagedPanels();
    };

    document.getElementById('network-form-reset')?.addEventListener('click', reset);
    bridgeSelect?.addEventListener('change', syncBridgeInput);
    bridgeInput?.addEventListener('input', renderBridgeSummary);
    managedMode?.addEventListener('change', syncManagedPanels);

    document.querySelectorAll('.js-edit-network').forEach((button) => {
      button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.network || '{}');
        document.getElementById('network-id').value = item.id || '';
        document.getElementById('network-name').value = item.name || '';
        document.getElementById('network-cidr').value = item.cidr || '';
        document.getElementById('network-gateway').value = item.gateway || '';
        document.getElementById('network-dhcp-start').value = item.dhcp_start || '';
        document.getElementById('network-dhcp-end').value = item.dhcp_end || '';
        document.getElementById('network-ipv6-cidr').value = item.ipv6_cidr || '';
        document.getElementById('network-ipv6-gateway').value = item.ipv6_gateway || '';
        document.getElementById('network-libvirt-managed').value = String(item.libvirt_managed || 0);
        document.getElementById('network-ipv4-pool-start').value = item.ipv4_pool?.start_ip || '';
        document.getElementById('network-ipv4-pool-end').value = item.ipv4_pool?.end_ip || '';
        document.getElementById('network-ipv4-pool-dns').value = item.ipv4_pool?.dns_servers || '';
        document.getElementById('network-ipv6-pool-start').value = item.ipv6_pool?.start_ip || '';
        document.getElementById('network-ipv6-pool-end').value = item.ipv6_pool?.end_ip || '';
        document.getElementById('network-ipv6-pool-dns').value = item.ipv6_pool?.dns_servers || '';
        editState.textContent = `编辑兼容项 #${item.id}：${item.name}`;
        editState.classList.remove('hidden-block');
        if (poolPanel) {
          poolPanel.open = Boolean(item.ipv4_pool?.start_ip || item.ipv6_pool?.start_ip);
        }
        applyBridgeValue(item.bridge_name || '');
        syncManagedPanels();
        form.scrollIntoView({behavior: 'smooth', block: 'start'});
      });
    });

    reset();
  }

  function initTemplatePage() {
    const form = document.getElementById('template-form');
    if (!form) return;

    const nicContainer = document.getElementById('template-nic-editor');
    const nicHidden = document.getElementById('template-nics-json');
    const nicPreview = document.getElementById('template-nics-preview');
    const diskContainer = document.getElementById('template-disk-editor');
    const diskHidden = document.getElementById('template-disks-json');
    const diskPreview = document.getElementById('template-disks-preview');
    const fallbackNetwork = document.getElementById('template-fallback-network');
    const state = document.getElementById('template-edit-state');
    const locks = document.getElementById('template-edit-locks');

    const lockedFields = ['template-image-id', 'template-virtualization-mode', 'template-machine-type', 'template-firmware-type'];
    const applyLocks = (locked) => {
      lockedFields.forEach((id) => {
        const node = document.getElementById(id);
        if (node) node.disabled = locked;
      });
      locks.textContent = locked ? '该模板已经派生出虚拟机，基础镜像 / 虚拟化模式 / 主板 / 固件已锁定；其余设置仍可调整供后续新建 VM 使用。' : '';
      locks.classList.toggle('hidden-block', !locked);
    };

    const reset = () => {
      form.reset();
      document.getElementById('template-id').value = '';
      document.getElementById('template-name').value = '';
      document.getElementById('template-vmid-hint').value = '';
      document.getElementById('template-os-type').value = 'l26';
      document.getElementById('template-os-source').value = 'image';
      document.getElementById('template-os-variant').value = 'generic';
      document.getElementById('template-virtualization-mode').value = 'kvm';
      document.getElementById('template-machine-type').value = 'pc';
      document.getElementById('template-firmware-type').value = 'bios';
      document.getElementById('template-scsi-controller').value = 'virtio-scsi-single';
      document.getElementById('template-qemu-agent-enabled').checked = true;
      document.getElementById('template-display-type').value = 'vnc';
      document.getElementById('template-serial-console-enabled').checked = true;
      document.getElementById('template-gpu-type').value = 'cirrus';
      document.getElementById('template-cpu-sockets').value = '1';
      document.getElementById('template-cpu-cores').value = '2';
      document.getElementById('template-cpu-threads').value = '1';
      document.getElementById('template-cpu-type').value = 'host';
      document.getElementById('template-cpu-numa').checked = false;
      document.getElementById('template-cpu-limit-percent').value = '';
      document.getElementById('template-cpu-units').value = '';
      document.getElementById('template-memory-mb').value = '2048';
      document.getElementById('template-memory-min-mb').value = '';
      document.getElementById('template-memory-max-mb').value = '4096';
      document.getElementById('template-balloon-enabled').checked = true;
      document.getElementById('template-memory-overcommit').value = '100';
      document.getElementById('template-disk-size-gb').value = '20';
      document.getElementById('template-disk-bus').value = 'virtio';
      document.getElementById('template-disks-json-override').value = '';
      document.getElementById('template-cloud-init-hostname').value = '';
      document.getElementById('template-cloud-init-dns-servers').value = '';
      document.getElementById('template-cloud-init-search-domain').value = '';
      document.getElementById('template-cloud-init-extra-user-data').value = '';
      fallbackNetwork.value = String(preferredBinding().network_name || preferredNetwork().name || 'default');
      state.classList.add('hidden-block');
      state.textContent = '';
      applyLocks(false);
      renderDiskEditor(diskContainer, diskHidden, diskPreview, [defaultDisk(0)]);
      renderNicEditor(nicContainer, nicHidden, nicPreview, [defaultNic(preferredBinding())], {
        onChange: (nics) => {
          const first = nics[0] || {};
          fallbackNetwork.value = primaryNetworkNameForNic(first);
        },
      });
    };

    document.getElementById('template-form-reset')?.addEventListener('click', reset);
    document.querySelectorAll('.js-add-disk[data-editor="template"]').forEach((button) => {
      button.addEventListener('click', () => addDisk(diskContainer, diskHidden, diskPreview, defaultDisk(diskContainer.querySelectorAll('.disk-card').length)));
    });
    document.querySelectorAll('.js-add-nic[data-editor="template"]').forEach((button) => {
      button.addEventListener('click', () => addNic(nicContainer, nicHidden, nicPreview, defaultNic(preferredBinding()), {
        onChange: (nics) => {
          const first = nics[0] || {};
          fallbackNetwork.value = primaryNetworkNameForNic(first);
        },
      }));
    });

    document.querySelectorAll('.js-edit-template').forEach((button) => {
      button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.template || '{}');
        document.getElementById('template-id').value = item.id || '';
        document.getElementById('template-name').value = item.name || '';
        document.getElementById('template-vmid-hint').value = item.vmid_hint || '';
        document.getElementById('template-os-type').value = item.os_type || 'l26';
        document.getElementById('template-os-source').value = item.os_source || 'image';
        document.getElementById('template-image-id').value = item.image_id || '';
        document.getElementById('template-os-variant').value = item.os_variant || 'generic';
        document.getElementById('template-virtualization-mode').value = item.virtualization_mode || 'kvm';
        document.getElementById('template-machine-type').value = item.machine_type || 'pc';
        document.getElementById('template-firmware-type').value = item.firmware_type || 'bios';
        document.getElementById('template-scsi-controller').value = item.scsi_controller || 'virtio-scsi-single';
        document.getElementById('template-qemu-agent-enabled').checked = Number(item.qemu_agent_enabled ?? 1) === 1;
        document.getElementById('template-display-type').value = item.display_type || 'vnc';
        document.getElementById('template-serial-console-enabled').checked = Number(item.serial_console_enabled ?? 1) === 1;
        document.getElementById('template-gpu-type').value = item.gpu_type || 'cirrus';
        document.getElementById('template-cpu-sockets').value = item.cpu_sockets || 1;
        document.getElementById('template-cpu-cores').value = item.cpu_cores || 1;
        document.getElementById('template-cpu-threads').value = item.cpu_threads || 1;
        document.getElementById('template-cpu-type').value = item.cpu_type || 'host';
        document.getElementById('template-cpu-numa').checked = Number(item.cpu_numa || 0) === 1;
        document.getElementById('template-cpu-limit-percent').value = item.cpu_limit_percent || '';
        document.getElementById('template-cpu-units').value = item.cpu_units || '';
        document.getElementById('template-memory-mb').value = item.memory_mb || 2048;
        document.getElementById('template-memory-min-mb').value = item.memory_min_mb || '';
        document.getElementById('template-memory-max-mb').value = item.memory_max_mb || '';
        document.getElementById('template-balloon-enabled').checked = Number(item.balloon_enabled ?? 1) === 1;
        document.getElementById('template-memory-overcommit').value = item.memory_overcommit_percent || 100;
        document.getElementById('template-disk-size-gb').value = item.disk_size_gb || 20;
        document.getElementById('template-disk-bus').value = item.disk_bus || 'virtio';
        document.getElementById('template-disk-overcommit').checked = Number(item.disk_overcommit_enabled || 0) === 1;
        document.getElementById('template-cloud-init-enabled').checked = Number(item.cloud_init_enabled || 0) === 1;
        document.getElementById('template-cloud-init-user').value = item.cloud_init_user || 'ubuntu';
        document.getElementById('template-cloud-init-password').value = item.cloud_init_password || '';
        document.getElementById('template-cloud-init-ssh-key').value = item.cloud_init_ssh_key || '';
        document.getElementById('template-cloud-init-hostname').value = item.cloud_init_hostname || '';
        document.getElementById('template-cloud-init-dns-servers').value = item.cloud_init_dns_servers || '';
        document.getElementById('template-cloud-init-search-domain').value = item.cloud_init_search_domain || '';
        document.getElementById('template-cloud-init-extra-user-data').value = item.cloud_init_extra_user_data || '';
        document.getElementById('template-autostart-default').checked = Number(item.autostart_default || 0) === 1;
        document.getElementById('template-notes').value = item.notes || '';
        document.getElementById('template-disks-json-override').value = '';
        fallbackNetwork.value = item.network_name || String(preferredBinding().network_name || preferredNetwork().name || 'default');
        state.textContent = `编辑模板 #${item.id}：${item.name}`;
        state.classList.remove('hidden-block');
        applyLocks(Number(item.linked_vm_count || 0) > 0);
        renderDiskEditor(diskContainer, diskHidden, diskPreview, item.disks || [defaultDisk(0)]);
        renderNicEditor(nicContainer, nicHidden, nicPreview, item.nics || [defaultNic(preferredBinding())], {
          onChange: (nics) => {
            const first = nics[0] || {};
            fallbackNetwork.value = primaryNetworkNameForNic(first);
          },
        });
        form.scrollIntoView({behavior: 'smooth', block: 'start'});
      });
    });

    form.addEventListener('submit', () => {
      nicContainer._ensurePreview?.();
      diskContainer._ensurePreview?.();
    });

    reset();
  }

  function renderTemplateSummary(templateId) {
    const target = document.getElementById('vm-template-nic-summary');
    const template = templateMap[String(templateId)];
    if (!target) return;
    if (!template || !Array.isArray(template.nics) || !template.nics.length) {
      target.innerHTML = '<div class="muted">默认继承模板里的 net0 / net1 / ipconfig0 / ipconfig1 拓扑。</div>';
      return;
    }
    target.innerHTML = template.nics.map((nic, index) => {
      const item = normalizeNic(nic);
      const binding = resolveBinding(item);
      return `<div class="muted">net${index}: ${escapeHtml(netPreviewText(binding, item))} / ipconfig${index}: ${escapeHtml(ipconfigPreviewText(item))}</div>`;
    }).join('');
  }

  function initVmPage() {
    const form = document.getElementById('vm-form');
    if (!form) return;

    const canEditVmNicConfig = (status) => {
      const text = String(status || '').toLowerCase();
      return text.includes('shut') || text.includes('defined') || text.includes('shutdown');
    };

    const vmContainer = document.getElementById('vm-nic-editor');
    const vmHidden = document.getElementById('vm-nics-json');
    const vmPreview = document.getElementById('vm-nics-preview');
    renderNicEditor(vmContainer, vmHidden, vmPreview, [defaultNic(preferredBinding())]);

    document.querySelectorAll('.js-add-nic[data-editor="vm"]').forEach((button) => {
      button.addEventListener('click', () => addNic(vmContainer, vmHidden, vmPreview, defaultNic(preferredBinding())));
    });

    const templateSelect = document.getElementById('vm-template-id');
    const customToggle = document.getElementById('vm-custom-nics-enabled');
    const customWrap = document.getElementById('vm-custom-nics-wrap');
    const syncVmEditor = () => {
      renderTemplateSummary(templateSelect?.value || '');
      if (!customToggle?.checked) {
        vmHidden.value = '';
        customWrap?.classList.add('hidden-block');
        return;
      }
      const template = templateMap[String(templateSelect?.value || '')];
      const nics = Array.isArray(template?.nics) && template.nics.length ? template.nics : [defaultNic(preferredBinding())];
      renderNicEditor(vmContainer, vmHidden, vmPreview, nics.map(normalizeNic));
      customWrap?.classList.remove('hidden-block');
    };

    templateSelect?.addEventListener('change', syncVmEditor);
    customToggle?.addEventListener('change', syncVmEditor);
    form.addEventListener('submit', () => {
      if (!customToggle?.checked) {
        vmHidden.value = '';
      } else {
        vmContainer._ensurePreview?.();
      }
    });
    syncVmEditor();

    const editForm = document.getElementById('vm-edit-form');
    const editState = document.getElementById('vm-edit-state');
    const editLocks = document.getElementById('vm-edit-locks');
    const editNicContainer = document.getElementById('vm-edit-nic-editor');
    const editNicHidden = document.getElementById('vm-edit-nics-json');
    const editNicPreview = document.getElementById('vm-edit-nics-preview');
    const editAddButtons = document.querySelectorAll('.js-add-nic[data-editor="vm-edit"]');

    const applyVmEditLock = (locked, statusText = '') => {
      editNicContainer?._setReadOnly?.(locked);
      editAddButtons.forEach((button) => {
        button.disabled = locked;
      });
      if (!editLocks) return;
      if (!statusText) {
        editLocks.textContent = '';
        editLocks.classList.add('hidden-block');
        return;
      }
      editLocks.textContent = locked
        ? `当前 VM 状态为 ${statusText}，netX / ipconfigX 只读展示；如需修改 Bridge / VLAN / IP 配置，请先关机。`
        : `当前 VM 状态为 ${statusText}，可调整 netX / ipconfigX；若变更 pool 拓扑，保存后地址可能重新分配。`;
      editLocks.classList.remove('hidden-block');
    };

    const resetEdit = () => {
      editForm.reset();
      document.getElementById('vm-edit-id').value = '';
      document.getElementById('vm-edit-name').value = '';
      document.getElementById('vm-edit-template').value = '';
      document.getElementById('vm-edit-cloud-init-user-override').value = '';
      document.getElementById('vm-edit-cloud-init-password-override').value = '';
      document.getElementById('vm-edit-cloud-init-ssh-key-override').value = '';
      document.getElementById('vm-edit-cloud-init-hostname-override').value = '';
      document.getElementById('vm-edit-cloud-init-dns-override').value = '';
      document.getElementById('vm-edit-cloud-init-search-domain-override').value = '';
      document.getElementById('vm-edit-cloud-init-extra-user-data-override').value = '';
      editState.textContent = '';
      editState.classList.add('hidden-block');
      renderNicEditor(editNicContainer, editNicHidden, editNicPreview, [defaultNic(preferredBinding())], {readOnly: true});
      applyVmEditLock(true, '');
    };

    editAddButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (button.disabled) return;
        addNic(editNicContainer, editNicHidden, editNicPreview, defaultNic(preferredBinding()));
      });
    });

    document.getElementById('vm-edit-reset')?.addEventListener('click', resetEdit);
    document.querySelectorAll('.js-edit-vm').forEach((button) => {
      button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.vm || '{}');
        const fullItem = vmMap[String(item.id)] || item;
        const editable = canEditVmNicConfig(fullItem.status || item.status || 'unknown');
        const nics = Array.isArray(fullItem.nics) && fullItem.nics.length ? fullItem.nics : [defaultNic(preferredBinding())];

        document.getElementById('vm-edit-id').value = item.id || '';
        document.getElementById('vm-edit-name').value = `${item.name || ''} / ${item.status || 'unknown'}`;
        document.getElementById('vm-edit-template').value = item.template_name || fullItem.template_name || '';
        document.getElementById('vm-edit-cpu-sockets').value = item.cpu_sockets || 1;
        document.getElementById('vm-edit-cpu-cores').value = item.cpu_cores || 1;
        document.getElementById('vm-edit-cpu-threads').value = item.cpu_threads || 1;
        document.getElementById('vm-edit-memory-mb').value = item.memory_mb || 2048;
        document.getElementById('vm-edit-expires-at').value = normalizeDatetimeLocal(item.expires_at || '');
        document.getElementById('vm-edit-expire-grace-days').value = item.expire_grace_days || 3;
        document.getElementById('vm-edit-cloud-init-user-override').value = fullItem.cloud_init_user_override || item.cloud_init_user_override || '';
        document.getElementById('vm-edit-cloud-init-password-override').value = fullItem.cloud_init_password_override || item.cloud_init_password_override || '';
        document.getElementById('vm-edit-cloud-init-ssh-key-override').value = fullItem.cloud_init_ssh_key_override || item.cloud_init_ssh_key_override || '';
        document.getElementById('vm-edit-cloud-init-hostname-override').value = fullItem.cloud_init_hostname_override || item.cloud_init_hostname_override || '';
        document.getElementById('vm-edit-cloud-init-dns-override').value = fullItem.cloud_init_dns_override || item.cloud_init_dns_override || '';
        document.getElementById('vm-edit-cloud-init-search-domain-override').value = fullItem.cloud_init_search_domain_override || item.cloud_init_search_domain_override || '';
        document.getElementById('vm-edit-cloud-init-extra-user-data-override').value = fullItem.cloud_init_extra_user_data_override || item.cloud_init_extra_user_data_override || '';
        renderNicEditor(editNicContainer, editNicHidden, editNicPreview, nics.map(normalizeNic), {readOnly: !editable});
        applyVmEditLock(!editable, fullItem.status || item.status || 'unknown');
        editState.textContent = `编辑 VM #${item.id}：${item.name}`;
        editState.classList.remove('hidden-block');
        editForm.scrollIntoView({behavior: 'smooth', block: 'start'});
      });
    });

    editForm.addEventListener('submit', (event) => {
      if (!document.getElementById('vm-edit-id').value) {
        event.preventDefault();
        alert('请先从右侧 VM 列表中选择要编辑的虚拟机。');
        return;
      }
      editNicContainer?._ensurePreview?.();
    });

    resetEdit();
  }

  initNetworkPage();
  initTemplatePage();
  initVmPage();
})();

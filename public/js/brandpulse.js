(() => {
  'use strict';

  const CONTAINER_ID = 'brandpulse-header-counters';

  const rootDoc = window.CFG_GLPI?.root_doc || '';
  const currentScriptUrl = document.currentScript?.src || '';
  const defaultPluginBaseUrl = rootDoc + '/plugins/brandpulse';
  const detectPluginBaseUrl = () => {
    const match = currentScriptUrl.match(/^(.*\/(?:plugins|marketplace)\/brandpulse)\/(?:public\/)?js\/brandpulse\.js(?:\?.*)?$/);
    return match?.[1] || defaultPluginBaseUrl;
  };
  const pluginBaseUrl = detectPluginBaseUrl();
  const countersEndpoint = pluginBaseUrl + '/ajax/counters.php';
  const assetManagementEndpoint = pluginBaseUrl + '/ajax/asset-management-link.php';
  const brandingEndpoint = pluginBaseUrl + '/ajax/branding.php';
  const iconIndexEndpoint = pluginBaseUrl + '/ajax/icons.php';
  const iconStaticIndexEndpoint = pluginBaseUrl + '/icons/pulse/index.json';
  const iconAssetEndpoint = pluginBaseUrl + '/ajax/icon.php?file=';

  const t = (message) => (typeof window.__ === 'function' ? window.__(message, 'brandpulse') : message);
  const isHtmlDocument = () => document.contentType.toLowerCase().includes('html')
    && document.documentElement?.nodeName?.toLowerCase() === 'html';
  let refreshTimer = null;
  let countersLoading = false;
  let freshRefreshPending = false;
  let lastRefreshPayload = { refresh_interval: 60 };
  let counterRefreshReady = false;
  let assetManagementObserver = null;
  const brandingCacheKey = 'brandpulse:branding-payload:v2:' + pluginBaseUrl;
  const brandingCacheMaxAge = 30 * 60 * 1000;

  const cacheStorage = () => {
    try {
      return window.localStorage || window.sessionStorage || null;
    } catch (error) {
      return null;
    }
  };

  const resolveAssetUrl = (value) => {
    if (!value) {
      return '';
    }

    if (/^(https?:)?\/\//.test(value) || value.startsWith('data:') || value.startsWith('/')) {
      return value;
    }

    return rootDoc + '/' + value.replace(/^\/+/, '');
  };

  const parseRgb = (value) => {
    const match = String(value || '').match(/rgba?\(([^)]+)\)/i);
    if (!match) {
      return null;
    }

    const parts = match[1].split(',').map((part) => Number.parseFloat(part.trim()));
    if (parts.length < 3 || parts.some((part, index) => index < 3 && Number.isNaN(part))) {
      return null;
    }

    return {
      r: parts[0],
      g: parts[1],
      b: parts[2],
      a: parts.length > 3 && !Number.isNaN(parts[3]) ? parts[3] : 1,
    };
  };

  const relativeLuminance = ({ r, g, b }) => {
    const normalize = (channel) => {
      const value = channel / 255;
      return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * normalize(r) + 0.7152 * normalize(g) + 0.0722 * normalize(b);
  };

  const readableColorFor = (element) => {
    const header = element.closest('header, .navbar, .navbar-expand, .topbar, .page-header') || element.parentElement;
    if (header) {
      const headerColor = parseRgb(window.getComputedStyle(header).color);
      if (headerColor && headerColor.a > 0.35) {
        return 'rgb(' + [headerColor.r, headerColor.g, headerColor.b].join(', ') + ')';
      }
    }

    let current = element;
    while (current && current !== document.documentElement) {
      const color = parseRgb(window.getComputedStyle(current).backgroundColor);
      if (color && color.a > 0.2) {
        return relativeLuminance(color) < 0.46 ? '#ffffff' : '#1f2937';
      }
      current = current.parentElement;
    }

    return document.documentElement.matches('[data-bs-theme="dark"], .theme-dark') ? '#ffffff' : '#1f2937';
  };

  const setReadableIconColor = (element) => {
    if (element) {
      element.style.setProperty('--brandpulse-icon-color', readableColorFor(element));
    }
  };

  const cssUrl = (url) => 'url("' + String(url).replace(/["\\\n\r]/g, '') + '")';
  const isSidebarElement = (element) => Boolean(element?.closest?.('#navbar-menu, aside, .navbar-vertical'));
  const isVisibleElement = (element) => Boolean(element?.offsetParent || element?.getClientRects?.().length);

  const findHeaderTarget = () => {
    const selectors = [
      'body > .page header[data-testid="main-header"] .user-menu',
      'header[data-testid="main-header"] .user-menu',
      'body > .page header.navbar .navbar-nav.flex-row.order-md-last.user-menu',
      'header.navbar .navbar-nav.flex-row.order-md-last.user-menu',
      '.navbar:not(#navbar-menu) .navbar-nav.flex-row.order-md-last',
      '.navbar:not(#navbar-menu) .navbar-nav.ms-auto',
      'header.navbar',
    ];

    for (const selector of selectors) {
      for (const target of document.querySelectorAll(selector)) {
        if (target && !isSidebarElement(target) && isVisibleElement(target)) {
          return target;
        }
      }
    }

    return null;
  };

  const ensureContainer = () => {
    const existing = document.getElementById(CONTAINER_ID);
    if (existing) {
      setReadableIconColor(existing);
      return existing;
    }

    const target = findHeaderTarget();
    if (!target) {
      return null;
    }

    const container = document.createElement('nav');
    container.id = CONTAINER_ID;
    container.className = 'brandpulse-counters';
    container.setAttribute('aria-label', t('GLPI BrandPulse counters'));
    setReadableIconColor(target);
    setReadableIconColor(container);
    target.prepend(container);

    return container;
  };

  const resolveIconUrl = (icon) => {
    if (!icon) {
      return iconAssetEndpoint + encodeURIComponent('Notifications/Bell.svg');
    }

    if (icon.startsWith('pulse:')) {
      const iconPath = icon.substring(6);
      const path = iconPath.endsWith('.svg') ? iconPath : iconPath + '.svg';
      return iconAssetEndpoint + encodeURIComponent(path);
    }

    if (/^(https?:)?\/\//.test(icon) || icon.startsWith('data:') || icon.startsWith('/')) {
      return icon;
    }

    if (icon.endsWith('.svg')) {
      return resolveAssetUrl(icon);
    }

    return null;
  };

  const setMaskIcon = (element, iconUrl) => {
    element.style.webkitMaskImage = cssUrl(iconUrl);
    element.style.maskImage = cssUrl(iconUrl);
  };

  const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[char]);

  const renderInlineMarkdown = (value) => escapeHtml(value)
    .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

  const renderAlertMessage = (value) => {
    const lines = String(value || '').replace(/\r\n/g, '\n').split('\n');
    const html = [];
    let listOpen = false;

    const closeList = () => {
      if (listOpen) {
        html.push('</ul>');
        listOpen = false;
      }
    };

    for (const rawLine of lines) {
      const line = rawLine.trim();
      if (line === '') {
        closeList();
        continue;
      }
      if (line.startsWith('- ')) {
        if (!listOpen) {
          html.push('<ul>');
          listOpen = true;
        }
        html.push('<li>' + renderInlineMarkdown(line.slice(2)) + '</li>');
        continue;
      }

      closeList();
      if (line.startsWith('## ')) {
        html.push('<h3>' + renderInlineMarkdown(line.slice(3)) + '</h3>');
      } else if (line.startsWith('# ')) {
        html.push('<h2>' + renderInlineMarkdown(line.slice(2)) + '</h2>');
      } else {
        html.push('<p>' + renderInlineMarkdown(line) + '</p>');
      }
    }

    closeList();
    return html.join('');
  };

  const cacheBrandingPayload = (payload) => {
    try {
      if (!payload?.branding?.enabled) {
        cacheStorage()?.removeItem(brandingCacheKey);
        return;
      }

      cacheStorage()?.setItem(brandingCacheKey, JSON.stringify({
        payload,
        stored_at: Date.now(),
      }));
    } catch (error) {
      window.console?.debug?.(t('BrandPulse branding unavailable'), error);
    }
  };

  const cachedBrandingPayload = () => {
    try {
      const cached = JSON.parse(cacheStorage()?.getItem(brandingCacheKey) || 'null');
      if (!cached?.payload?.branding?.enabled) {
        return null;
      }
      if (Date.now() - Number(cached.stored_at || 0) > brandingCacheMaxAge) {
        cacheStorage()?.removeItem(brandingCacheKey);
        return null;
      }

      return cached.payload;
    } catch (error) {
      return null;
    }
  };

  const renderIcon = (counter) => {
    const icon = counter.icon || 'pulse:Notifications/Bell.svg';
    const iconUrl = counter.icon_url || resolveIconUrl(icon);

    if (iconUrl) {
      const element = document.createElement('span');
      element.className = 'brandpulse-icon';
      element.setAttribute('aria-hidden', 'true');
      setMaskIcon(element, iconUrl);
      return element;
    }

    const element = document.createElement('i');
    element.className = icon;
    element.setAttribute('aria-hidden', 'true');
    return element;
  };

  const setupCompactSearch = (enabled) => {
    document.body.classList.toggle('brandpulse-compact-search-enabled', Boolean(enabled));

    if (!enabled) {
      for (const container of document.querySelectorAll('.brandpulse-compact-search')) {
        container.querySelector('.brandpulse-search-trigger')?.remove();
        for (const nativeControl of container.querySelectorAll('.brandpulse-native-search-control')) {
          nativeControl.classList.remove('brandpulse-native-search-control');
        }
        container.classList.remove('brandpulse-compact-search', 'is-expanded');
        container.removeAttribute('data-brandpulse-compact-search');
      }
      return;
    }

    const inputs = document.querySelectorAll([
      'header[data-testid="main-header"] #global-search',
      '.secondary-bar #global-search',
      'header.navbar input[name="globalsearch"]',
      '.navbar:not(#navbar-menu) input[name="globalsearch"]',
    ].join(','));

    for (const input of inputs) {
      if (isSidebarElement(input) || input.type === 'hidden' || !isVisibleElement(input)) {
        continue;
      }

      const container = input.closest('form[role="search"]')
        || input.closest('form')
        || input.closest('.input-group, .search, .navbar-search')
        || input.parentElement;
      if (!container || container.dataset.brandpulseCompactSearch === '1' || isSidebarElement(container)) {
        continue;
      }

      container.dataset.brandpulseCompactSearch = '1';
      container.classList.add('brandpulse-compact-search');
      setReadableIconColor(container);

      const trigger = document.createElement('button');
      trigger.className = 'brandpulse-search-trigger';
      trigger.type = 'button';
      trigger.title = t('Search');
      trigger.setAttribute('aria-label', t('Search'));

      const icon = document.createElement('span');
      icon.className = 'brandpulse-search-trigger-icon';
      icon.setAttribute('aria-hidden', 'true');
      setMaskIcon(icon, iconAssetEndpoint + encodeURIComponent('Search/Magnifer.svg'));
      trigger.append(icon);

      input.before(trigger);
      const inputParent = input.parentElement;
      for (const nativeControl of inputParent.querySelectorAll('button, .input-group-text, .input-group-addon')) {
        if (nativeControl !== trigger && !nativeControl.contains(input)) {
          nativeControl.classList.add('brandpulse-native-search-control');
        }
      }

      const expand = () => {
        container.classList.add('is-expanded');
        input.focus();
      };

      const collapseIfEmpty = () => {
        if (!input.value) {
          container.classList.remove('is-expanded');
        }
      };

      trigger.addEventListener('click', expand);
      input.addEventListener('focus', () => container.classList.add('is-expanded'));
      input.addEventListener('blur', () => window.setTimeout(collapseIfEmpty, 150));
    }
  };

  const counterSignature = (counter, index) => [
    index,
    counter.key || '',
    counter.label || '',
    counter.icon || '',
    counter.icon_url || '',
  ].join('|');

  const updateCounterElement = (item, counter) => {
    const href = counter.href || '#';
    item.href = href;
    item.title = counter.label || counter.key || '';
    item.setAttribute('aria-label', (counter.label || counter.key) + ': ' + counter.count);

    const badge = item.querySelector('.brandpulse-badge');
    if (badge) {
      badge.textContent = String(counter.count ?? 0);
      badge.style.backgroundColor = counter.color || '#3b82f6';
    }
  };

  const renderCounter = (counter, index) => {
    const item = document.createElement('a');
    item.className = 'brandpulse-counter';
    item.dataset.brandpulseCounterSignature = counterSignature(counter, index);
    item.addEventListener('click', (event) => {
      if (item.getAttribute('href') === '#') {
        event.preventDefault();
      }
    });

    const badge = document.createElement('span');
    badge.className = 'brandpulse-badge';

    item.append(renderIcon(counter), badge);
    updateCounterElement(item, counter);

    return item;
  };

  const render = (payload) => {
    if (!payload.enabled || !Array.isArray(payload.counters)) {
      document.getElementById(CONTAINER_ID)?.remove();
      return;
    }

    const container = ensureContainer();
    if (!container) {
      return;
    }

    setReadableIconColor(container);
    const existingCounters = [...container.querySelectorAll('.brandpulse-counter')];
    const canUpdateCounters = existingCounters.length === payload.counters.length
      && payload.counters.every((counter, index) => (
        existingCounters[index]?.dataset.brandpulseCounterSignature === counterSignature(counter, index)
      ));

    if (canUpdateCounters) {
      payload.counters.forEach((counter, index) => updateCounterElement(existingCounters[index], counter));
      return;
    }

    container.replaceChildren();
    container.hidden = false;
    payload.counters.forEach((counter, index) => {
      container.append(renderCounter(counter, index));
    });
  };

  const applyBranding = (payload) => {
    const branding = payload?.branding;
    if (!branding?.enabled) {
      document.querySelector('.brandpulse-login-alert')?.remove();
      return;
    }

    if (branding.title) {
      document.title = branding.title;
    }

    const faviconUrl = resolveAssetUrl(branding.favicon);
    if (faviconUrl) {
      let link = document.querySelector('link[rel="icon"]');
      if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.append(link);
      }
      link.href = faviconUrl;
    }

    if (branding.login_alert_enabled && branding.login_alert_message) {
      const loginContainer = document.querySelector('form[action*="login"]')?.parentElement
        || document.querySelector('.login-box, .page-anonymous .container');
      if (loginContainer) {
        let alert = document.querySelector('.brandpulse-login-alert');
        if (!alert) {
          alert = document.createElement('div');
          loginContainer.prepend(alert);
        }

        const message = String(branding.login_alert_message || '');
        const isLong = message.length > 180 || message.split(/\r?\n/).length > 3;
        const isExpanded = Boolean(branding.login_alert_expanded) || !isLong;

        alert.className = 'alert alert-' + (branding.login_alert_type || 'info') + ' brandpulse-login-alert'
          + (isLong ? ' is-collapsible' : '')
          + (isExpanded ? ' is-expanded' : '');
        alert.innerHTML = '';

        const icon = document.createElement('span');
        icon.className = 'brandpulse-login-alert-icon';
        icon.setAttribute('aria-hidden', 'true');
        setMaskIcon(icon, resolveIconUrl(branding.login_alert_icon));

        const body = document.createElement('div');
        body.className = 'brandpulse-login-alert-body';
        body.innerHTML = renderAlertMessage(message);

        alert.append(icon, body);

        if (isLong) {
          const toggle = document.createElement('button');
          toggle.className = 'brandpulse-login-alert-toggle';
          toggle.type = 'button';
          toggle.title = t('Expand alert');
          toggle.setAttribute('aria-label', t('Expand alert'));
          toggle.addEventListener('click', () => {
            alert.classList.toggle('is-expanded');
          });
          alert.append(toggle);
        }
      }
    }
  };



  const syncPulseTargetRow = (select) => {
    const row = select.closest('tr');
    if (!row) {
      return;
    }

    for (const target of row.querySelectorAll('[data-pulse-target]')) {
      target.hidden = target.dataset.pulseTarget !== select.value;
    }
  };

  const setupPulseTargets = () => {
    for (const select of document.querySelectorAll('[data-pulse-source]')) {
      syncPulseTargetRow(select);
    }

    document.addEventListener('change', (event) => {
      const select = event.target.closest('[data-pulse-source]');
      if (select) {
        syncPulseTargetRow(select);
      }
    });
  };

  const pulseRows = () => [...document.querySelectorAll('[data-pulse-rows] > [data-pulse-row]')];

  const renumberPulseRows = () => {
    pulseRows().forEach((row, index) => {
      for (const field of row.querySelectorAll('[name^="counters["]')) {
        field.name = field.name.replace(/counters\[[^\]]+\]/, 'counters[' + String(index) + ']');
      }
    });
  };

  const refreshPulseOrderControls = () => {
    const rows = pulseRows();
    const count = rows.length;
    document.querySelector('[data-pulse-row-count]')?.replaceChildren(String(count));

    rows.forEach((row, index) => {
      row.querySelector('[data-pulse-position]')?.replaceChildren(String(index + 1));
      const up = row.querySelector('[data-pulse-move="up"]');
      const down = row.querySelector('[data-pulse-move="down"]');
      if (up) {
        up.disabled = index === 0;
      }
      if (down) {
        down.disabled = index === count - 1;
      }
    });
    syncProfileCounterRows();
  };

  const nextPulseRowIndex = () => {
    let nextIndex = 0;
    for (const field of document.querySelectorAll('[name^="counters["]')) {
      const match = field.name.match(/^counters\[(\d+)\]/);
      if (match) {
        nextIndex = Math.max(nextIndex, Number.parseInt(match[1], 10) + 1);
      }
    }
    return nextIndex;
  };

  const clonePulseRowTemplate = () => {
    const template = document.querySelector('[data-pulse-row-template]');
    const tbody = document.querySelector('[data-pulse-rows]');
    if (!template || !tbody) {
      return;
    }

    const wrapper = document.createElement('tbody');
    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextPulseRowIndex()));
    const row = wrapper.querySelector('[data-pulse-row]');
    if (!row) {
      return;
    }

    tbody.append(row);
    syncPulseTargetRow(row.querySelector('[data-pulse-source]'));
    pulseChanged(row);
  };

  const setupPulseTableControls = () => {
    const table = document.querySelector('[data-pulse-table]');
    if (!table || table.dataset.ready) {
      return;
    }

    table.dataset.ready = '1';
    document.querySelector('[data-pulse-add]')?.addEventListener('click', clonePulseRowTemplate);

    table.addEventListener('click', (event) => {
      const button = event.target.closest('[data-pulse-move]');
      const row = button?.closest('[data-pulse-row]');
      if (!button || !row) {
        return;
      }

      if (button.dataset.pulseMove === 'up' && row.previousElementSibling) {
        row.previousElementSibling.before(row);
      }
      if (button.dataset.pulseMove === 'down' && row.nextElementSibling) {
        row.nextElementSibling.after(row);
      }

      pulseChanged(row);
    });

    table.closest('form')?.addEventListener('submit', renumberPulseRows);
    refreshPulseOrderControls();
  };

  let pulseDirty = false;
  let movedPulseRow = null;
  let movedPulseTimer = null;
  const activePulseRows = () => pulseRows().filter((row) =>
    row.querySelector('[data-profile-active], [data-global-active]')?.checked);
  const pulseRowLabel = (row) => row.querySelector('[name$="[label]"]')?.value || row.dataset.counterLabel || t('Counter');
  const pulseButton = (text, action, title = text) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-secondary';
    button.textContent = text;
    button.dataset.pulseComposeAction = action;
    button.title = title;
    button.setAttribute('aria-label', title);
    return button;
  };
  const markPulseDirty = () => {
    pulseDirty = true;
    const status = document.querySelector('[data-pulse-dirty-status]');
    if (status) {
      status.textContent = t('Unsaved changes.');
      status.classList.remove('text-muted');
      status.classList.add('text-warning');
    }
  };
  const pulseChanged = (row = null) => {
    markPulseDirty();
    if (row) {
      movedPulseRow?.classList.remove('brandpulse-row-moved');
      movedPulseRow = row;
      row.classList.add('brandpulse-row-moved');
      window.clearTimeout(movedPulseTimer);
      movedPulseTimer = window.setTimeout(() => {
        movedPulseRow?.classList.remove('brandpulse-row-moved');
        movedPulseRow = null;
        document.querySelectorAll('.brandpulse-row-moved').forEach((item) => item.classList.remove('brandpulse-row-moved'));
      }, 1800);
    }
    refreshPulseOrderControls();
    if (row) {
      const position = activePulseRows().indexOf(row);
      const announcement = document.querySelector('[data-pulse-move-announcement]');
      if (announcement && position >= 0) {
        announcement.textContent = pulseRowLabel(row) + ' — ' + t('Position') + ' ' + (position + 1);
      }
    }
  };

  const syncProfileCounterRows = () => {
    const rows = pulseRows();
    rows.forEach((row) => {
      const active = row.querySelector('[data-profile-active], [data-global-active]');
      const mandatory = row.querySelector('[data-profile-mandatory]');
      if (mandatory) {
        mandatory.disabled = !active?.checked;
        if (!active?.checked) mandatory.checked = false;
      }
    });
    const limit = Math.max(6, Math.min(10, Number(document.querySelector('[data-profile-counter-limit]')?.value || 6)));
    const activeRows = activePulseRows();
    const count = document.querySelector('[data-pulse-active-count]');
    if (count) {
      count.textContent = activeRows.length + ' / ' + limit;
      count.classList.toggle('text-danger', activeRows.length > limit);
    }
    for (const [selector, selected] of [['[data-pulse-composition-active]', true], ['[data-pulse-composition-available]', false]]) {
      const container = document.querySelector(selector);
      if (!container) continue;
      container.replaceChildren();
      const selectedRows = selected ? activeRows : rows.filter((row) => !activeRows.includes(row));
      if (!selectedRows.length) {
        const empty = document.createElement('span');
        empty.className = 'text-muted';
        empty.textContent = t(selected ? 'No displayed counters.' : 'All counters are displayed.');
        container.append(empty);
      }
      selectedRows.forEach((row, index) => {
        const item = document.createElement('div');
        item.className = 'brandpulse-composition-item';
        item.classList.toggle('brandpulse-row-moved', row === movedPulseRow);
        item.dataset.pulseRowIndex = String(rows.indexOf(row));
        if (selected) {
          const drag = pulseButton('↕', 'drag', t('Drag to reorder') + ' — ' + pulseRowLabel(row));
          drag.draggable = true;
          drag.setAttribute('data-pulse-drag', '');
          item.append(drag);
        }
        const label = document.createElement('span');
        label.className = 'brandpulse-composition-label';
        label.textContent = (selected ? (index + 1) + '. ' : '') + pulseRowLabel(row);
        item.append(label);
        if (selected) {
          if (row.querySelector('[data-profile-mandatory]')?.checked) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary';
            badge.textContent = t('Required');
            item.append(badge);
          }
          const up = pulseButton('↑', 'up', t('Move up') + ' — ' + pulseRowLabel(row));
          const down = pulseButton('↓', 'down', t('Move down') + ' — ' + pulseRowLabel(row));
          up.disabled = index === 0;
          down.disabled = index === selectedRows.length - 1;
          item.append(up, down, pulseButton(t('Remove'), 'remove', t('Remove') + ' — ' + pulseRowLabel(row)));
        } else {
          const add = pulseButton(t('Add'), 'add', t('Add') + ' — ' + pulseRowLabel(row));
          add.disabled = activeRows.length >= limit;
          item.append(add);
        }
        container.append(item);
      });
    }
    const preview = document.querySelector('[data-profile-counter-preview]');
    if (preview) {
      preview.replaceChildren();
      if (!activeRows.length) {
        const empty = document.createElement('span');
        empty.className = 'text-muted';
        empty.textContent = t('No displayed counters.');
        preview.append(empty);
      }
      for (const row of activeRows.slice(0, limit)) {
        const item = document.createElement('span');
        item.className = 'brandpulse-profile-preview-counter';
        const icon = document.createElement('span');
        icon.className = 'brandpulse-icon-preview';
        icon.setAttribute('aria-hidden', 'true');
        const iconValue = row.querySelector('[data-icon-value]')?.value;
        const iconUrl = iconValue ? resolveIconUrl(iconValue) : row.dataset.counterIconUrl || '';
        icon.style.webkitMaskImage = 'url("' + iconUrl + '")';
        icon.style.maskImage = 'url("' + iconUrl + '")';
        const color = row.querySelector('[name$="[color]"]')?.value;
        if (color) icon.style.backgroundColor = color;
        item.append(icon, document.createTextNode('12 · ' + pulseRowLabel(row)));
        if (row.querySelector('[data-profile-mandatory]')?.checked) {
          const lock = document.createElement('i');
          lock.className = 'ti ti-lock';
          lock.setAttribute('aria-label', t('Required'));
          item.append(lock);
        }
        preview.append(item);
      }
    }
  };

  const setupProfileCounterControls = () => {
    const table = document.querySelector('[data-pulse-table]');
    if (!table || table.dataset.compositionReady) return;
    table.dataset.compositionReady = '1';
    const form = table.closest('form');
    let submitting = false;
    let accessDirty = false;
    form?.addEventListener('input', () => { markPulseDirty(); syncProfileCounterRows(); });
    form?.addEventListener('change', () => { markPulseDirty(); syncProfileCounterRows(); });
    form?.addEventListener('submit', (event) => {
      if (accessDirty && !window.confirm(t('Profile permission changes have not been saved. Continue without saving them?'))) {
        event.preventDefault();
        return;
      }
      submitting = true;
    });
    const accessForm = document.querySelector('[data-pulse-access-form]');
    accessForm?.addEventListener('change', (event) => {
      accessDirty = true;
      const label = event.target.closest('label')?.querySelector('.form-check-label');
      if (label) label.textContent = t(event.target.checked ? 'Authorized' : 'Pulse not visible');
    });
    accessForm?.addEventListener('submit', (event) => {
      if (pulseDirty && !window.confirm(t('Pulse composition changes have not been saved. Continue without saving them?'))) {
        event.preventDefault();
        return;
      }
      submitting = true;
    });
    window.addEventListener('beforeunload', (event) => {
      if ((pulseDirty || accessDirty) && !submitting) { event.preventDefault(); event.returnValue = ''; }
    });
    const profileSelect = document.querySelector('[data-pulse-profile-select]');
    profileSelect?.addEventListener('change', () => {
      if ((pulseDirty || accessDirty) && !window.confirm(t('Unsaved changes will be lost when changing profile. Continue?'))) {
        profileSelect.value = profileSelect.dataset.savedProfile;
        return;
      }
      submitting = true;
      const baseUrl = profileSelect.dataset.baseUrl || window.location.pathname;
      window.location.assign(baseUrl + '&profile_id=' + encodeURIComponent(profileSelect.value));
    });
    const rowFor = (element) => element?.closest('[data-pulse-row]')
      || pulseRows()[Number(element?.closest('[data-pulse-row-index]')?.dataset.pulseRowIndex)];
    document.querySelector('[data-pulse-composition]')?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-pulse-compose-action]');
      const row = rowFor(button);
      if (!row || !button || button.disabled) return;
      const action = button.dataset.pulseComposeAction;
      const active = row.querySelector('[data-profile-active], [data-global-active]');
      if (action === 'add' || action === 'remove') {
        const limit = Number(document.querySelector('[data-profile-counter-limit]')?.value || 6);
        if (action === 'add' && activePulseRows().length >= limit) return;
        active.checked = action === 'add';
      } else if (action === 'up' || action === 'down') {
        const rows = activePulseRows();
        const target = rows[rows.indexOf(row) + (action === 'up' ? -1 : 1)];
        if (!target) return;
        if (action === 'up') target.before(row); else target.after(row);
      } else return;
      const restoreFocus = document.activeElement === button || event.detail === 0;
      pulseChanged(row);
      if (restoreFocus) {
        const nextAction = action === 'add' ? 'remove' : action === 'remove' ? 'add' : action;
        const index = pulseRows().indexOf(row);
        const item = document.querySelector('[data-pulse-composition] [data-pulse-row-index="' + index + '"]');
        const next = item?.querySelector('[data-pulse-compose-action="' + nextAction + '"]:not(:disabled)')
          || item?.querySelector('button:not(:disabled)');
        next?.focus({ preventScroll: true });
      }
    });
    let draggedRow = null;
    document.addEventListener('dragstart', (event) => {
      const handle = event.target.closest('[data-pulse-drag]');
      draggedRow = rowFor(handle);
      if (!draggedRow || !handle) return;
      event.dataTransfer?.setData('text/plain', String(pulseRows().indexOf(draggedRow)));
      if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
      draggedRow.classList.add('brandpulse-row-dragging');
    });
    document.addEventListener('dragover', (event) => {
      if (!draggedRow || !rowFor(event.target)) return;
      event.preventDefault();
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
    });
    document.addEventListener('drop', (event) => {
      const target = rowFor(event.target);
      if (!draggedRow || !target || target === draggedRow) return;
      event.preventDefault();
      const moving = draggedRow;
      const box = (event.target.closest('[data-pulse-row-index], [data-pulse-row]')).getBoundingClientRect();
      if (event.clientY < box.top + box.height / 2) target.before(moving); else target.after(moving);
      moving.classList.remove('brandpulse-row-dragging');
      draggedRow = null;
      pulseChanged(moving);
    });
    document.addEventListener('dragend', () => {
      draggedRow?.classList.remove('brandpulse-row-dragging');
      draggedRow = null;
    });
    syncProfileCounterRows();
  };

  const setupPulseModeControls = () => {
    const choices = [...document.querySelectorAll('[data-pulse-mode]')];
    const selfservice = document.querySelector('[data-global-selfservice]');
    if (choices.length === 0 || !selfservice) return;
    const sync = () => {
      const mode = choices.find((choice) => choice.checked)?.value || 'global';
      selfservice.classList.toggle('d-none', mode !== 'global');
    };
    choices.forEach((choice) => choice.addEventListener('change', sync));
    sync();
  };

  const setupProfileCounterLimit = () => {
    const input = document.querySelector('[data-profile-counter-limit]');
    const output = document.querySelector('[data-profile-counter-limit-value]');
    if (!input || !output) return;
    const sync = () => {
      output.textContent = String(input.value);
      syncProfileCounterRows();
    };
    input.addEventListener('input', sync);
    sync();
  };

  let iconIndexPromise = null;
  let activeIconField = null;
  let iconPage = 0;
  const iconsPerPage = 24;
  let iconSearchTimer = null;
  const iconFilters = {
    category: '',
    query: '',
  };

  const normalizeIcon = (icon) => {
    const normalized = {
      path: String(icon.path || icon.p || ''),
      label: String(icon.label || icon.l || ''),
      category: String(icon.category || icon.c || ''),
      search: String(icon.search || icon.s || ''),
    };
    normalized.search = (normalized.search || [normalized.path, normalized.label, normalized.category].join(' ')).toLowerCase();
    return normalized;
  };

  const loadIconIndex = async () => {
    if (!iconIndexPromise) {
      const fetchIconIndex = (url) => fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      }).then((response) => (response.ok ? response.json() : null));

      iconIndexPromise = fetchIconIndex(iconIndexEndpoint)
        .then((index) => {
          const entries = Array.isArray(index?.icons) ? index.icons : (Array.isArray(index?.preferred) ? index.preferred : []);
          if (entries.length > 0) {
            return entries;
          }
          return fetchIconIndex(iconStaticIndexEndpoint).then((fallbackIndex) => (
            Array.isArray(fallbackIndex?.icons) ? fallbackIndex.icons : (Array.isArray(fallbackIndex?.preferred) ? fallbackIndex.preferred : [])
          ));
        })
        .then((entries) => entries.map(normalizeIcon).filter((icon) => icon.path !== ''))
        .catch(() => []);
    }

    return iconIndexPromise;
  };

  const iconMatches = (icon) => {
    if (iconFilters.category && icon.category !== iconFilters.category) {
      return false;
    }

    if (!iconFilters.query) {
      return true;
    }

    return iconFilters.query.split(/\s+/).every((word) => icon.search.includes(word));
  };

  const populateIconCategories = (modal, icons) => {
    const select = modal.querySelector('[data-icon-category]');
    if (!select || select.dataset.ready) {
      return;
    }

    const categories = [...new Set(icons.map((icon) => icon.category).filter(Boolean))]
      .sort((left, right) => left.localeCompare(right, undefined, { sensitivity: 'base' }));

    for (const category of categories) {
      const option = document.createElement('option');
      option.value = category;
      option.textContent = category;
      select.append(option);
    }

    select.dataset.ready = '1';
  };

  const updateIconField = (field, value, label) => {
    const input = field.querySelector('[data-icon-value]');
    const preview = field.querySelector('[data-icon-preview]');
    const textLabel = field.querySelector('[data-icon-label]');

    if (input) {
      input.value = value;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (preview) {
      setMaskIcon(preview, resolveIconUrl(value));
    }
    if (textLabel) {
      textLabel.textContent = label;
    }
  };

  const renderIconModal = async () => {
    const modal = document.querySelector('[data-icon-modal]');
    if (!modal) {
      return;
    }

    const allIcons = await loadIconIndex();
    populateIconCategories(modal, allIcons);
    const results = modal.querySelector('[data-icon-results]');
    const pageLabel = modal.querySelector('[data-icon-page]');
    const prev = modal.querySelector('[data-icon-prev]');
    const next = modal.querySelector('[data-icon-next]');

    const filtered = allIcons.filter(iconMatches);
    const pageCount = Math.max(1, Math.ceil(filtered.length / iconsPerPage));
    iconPage = Math.min(iconPage, pageCount - 1);
    const pageIcons = filtered.slice(iconPage * iconsPerPage, (iconPage + 1) * iconsPerPage);

    results.replaceChildren();
    for (const icon of pageIcons) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'brandpulse-icon-result';
      button.title = icon.category + ' / ' + icon.label;
      button.dataset.iconValue = 'pulse:' + icon.path;
      button.dataset.iconLabel = icon.category + ' / ' + icon.label;

      const preview = document.createElement('img');
      preview.className = 'brandpulse-icon-result-preview';
      preview.src = resolveIconUrl(button.dataset.iconValue);
      preview.alt = '';
      preview.loading = 'lazy';
      preview.decoding = 'async';
      preview.setAttribute('aria-hidden', 'true');
      preview.addEventListener('error', () => button.classList.add('brandpulse-icon-result-missing'), { once: true });

      const label = document.createElement('span');
      label.textContent = icon.label;

      button.append(preview, label);
      results.append(button);
    }

    if (pageLabel) {
      pageLabel.textContent = t('Page') + ' ' + String(iconPage + 1) + ' / ' + String(pageCount) + ' - ' + String(filtered.length) + ' ' + t('icons');
    }
    if (prev) {
      prev.disabled = iconPage <= 0;
    }
    if (next) {
      next.disabled = iconPage >= pageCount - 1;
    }
  };

  const openIconModal = async (field) => {
    const modal = document.querySelector('[data-icon-modal]');
    if (!modal) {
      return;
    }

    activeIconField = field;
    iconPage = 0;
    modal.hidden = false;
    document.body.classList.add('brandpulse-icon-modal-open');
    modal.querySelector('[data-icon-search]')?.focus();
    await renderIconModal();
  };

  const closeIconModal = () => {
    const modal = document.querySelector('[data-icon-modal]');
    if (modal) {
      modal.hidden = true;
    }
    activeIconField = null;
    document.body.classList.remove('brandpulse-icon-modal-open');
  };

  const setupIconPickerModal = () => {
    const modal = document.querySelector('[data-icon-modal]');
    if (!modal || modal.dataset.ready) {
      return;
    }

    modal.dataset.ready = '1';
    document.addEventListener('click', (event) => {
      const opener = event.target.closest('[data-icon-open]');
      if (opener) {
        const field = opener.closest('[data-icon-field]');
        if (field) {
          openIconModal(field);
        }
        return;
      }

      const selected = event.target.closest('.brandpulse-icon-result');
      if (selected && activeIconField) {
        updateIconField(activeIconField, selected.dataset.iconValue, selected.dataset.iconLabel);
        closeIconModal();
      }
    });

    modal.addEventListener('click', (event) => {
      if (event.target.closest('[data-icon-close]')) {
        closeIconModal();
      }
    });

    modal.querySelector('[data-icon-search]')?.addEventListener('input', (event) => {
      iconFilters.query = event.target.value.trim().toLowerCase();
      iconPage = 0;
      window.clearTimeout(iconSearchTimer);
      iconSearchTimer = window.setTimeout(renderIconModal, 140);
    });

    modal.querySelector('[data-icon-category]')?.addEventListener('change', (event) => {
      iconFilters.category = event.target.value;
      iconPage = 0;
      renderIconModal();
    });

    modal.querySelector('[data-icon-prev]')?.addEventListener('click', () => {
      iconPage = Math.max(0, iconPage - 1);
      renderIconModal();
    });

    modal.querySelector('[data-icon-next]')?.addEventListener('click', () => {
      iconPage += 1;
      renderIconModal();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) {
        closeIconModal();
      }
    });
  };

  const setupTicketViewModal = () => {
    const modal = document.querySelector('[data-pulse-view-modal]');
    if (!modal || modal.dataset.ready || !window.bootstrap?.Modal) {
      return;
    }
    modal.dataset.ready = '1';
    const options = JSON.parse(modal.dataset.viewOptions || '{}');
    const defaults = JSON.parse(modal.dataset.defaultColumns || '[]');
    const dialog = window.bootstrap.Modal.getOrCreateInstance(modal);
    const useGlpi = modal.querySelector('[data-view-use-glpi]');
    const custom = modal.querySelector('[data-view-custom]');
    const available = modal.querySelector('[data-view-available]');
    const list = modal.querySelector('[data-view-columns]');
    const sort = modal.querySelector('[data-view-sort]');
    const order = modal.querySelector('[data-view-order]');
    let activeRow = null;
    let columns = [];
    const validColumns = (values) => [...new Set((Array.isArray(values) ? values : []).map(Number))]
      .filter((id) => options[id] && !options[id].fixed);
    const syncMode = () => {
      custom.disabled = useGlpi.checked || Object.keys(options).length === 0;
    };
    const renderColumns = () => {
      list.replaceChildren();
      columns.forEach((id, index) => {
        const row = document.createElement('li');
        row.className = 'list-group-item d-flex align-items-center justify-content-between gap-2';
        const label = document.createElement('span');
        label.textContent = options[id].name;
        row.append(label);
        const controls = document.createElement('div');
        controls.className = 'btn-group btn-group-sm';
        for (const [action, title, text] of [['up', 'Move up', '↑'], ['down', 'Move down', '↓'], ['remove', 'Remove column', '×']]) {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'btn btn-outline-secondary';
          button.textContent = text;
          button.title = t(title);
          button.setAttribute('aria-label', t(title) + ' — ' + options[id].name);
          button.disabled = (action === 'up' && index === 0) || (action === 'down' && index === columns.length - 1);
          button.addEventListener('click', () => {
            if (action === 'remove') {
              columns.splice(index, 1);
            } else {
              const destination = index + (action === 'up' ? -1 : 1);
              [columns[index], columns[destination]] = [columns[destination], columns[index]];
            }
            renderColumns();
          });
          controls.append(button);
        }
        row.append(controls);
        list.append(row);
      });
      for (const option of available.options) {
        option.disabled = columns.includes(Number(option.value));
      }
      available.value = Array.from(available.options).find((option) => !option.disabled)?.value || '';
      modal.querySelector('[data-view-add]').disabled = !available.value;
    };

    document.addEventListener('click', (event) => {
      const opener = event.target.closest('[data-pulse-view-open]');
      if (!opener) {
        return;
      }
      activeRow = opener.closest('[data-pulse-row]');
      const input = activeRow.querySelector('[data-pulse-view]');
      let view = {};
      try {
        view = JSON.parse(input.value) || {};
      } catch (error) {
        // A malformed draft falls back to native preferences.
      }
      useGlpi.checked = view.use_glpi !== false;
      useGlpi.disabled = Object.keys(options).length === 0;
      columns = validColumns(useGlpi.checked && !view.columns?.length ? defaults : view.columns);
      sort.value = String(view.sort || 0);
      if (!sort.value) sort.value = '0';
      order.value = view.order === 'ASC' ? 'ASC' : 'DESC';
      modal.querySelector('[data-view-counter-name]').textContent = activeRow.querySelector('[name$="[label]"]')?.value || '';
      renderColumns();
      syncMode();
      dialog.show();
    });
    useGlpi.addEventListener('change', syncMode);
    modal.querySelector('[data-view-add]').addEventListener('click', () => {
      columns = validColumns([...columns, Number(available.value)]);
      renderColumns();
    });
    modal.querySelector('[data-view-apply]').addEventListener('click', () => {
      if (!activeRow) return;
      activeRow.querySelector('[data-pulse-view]').value = JSON.stringify({
        use_glpi: useGlpi.checked, columns, sort: Number(sort.value), order: order.value,
      });
      activeRow.querySelector('[data-pulse-view-label]').textContent = t(useGlpi.checked ? 'GLPI preferences' : 'Custom view');
      markPulseDirty();
      dialog.hide();
    });
    modal.addEventListener('hidden.bs.modal', () => { activeRow = null; });
  };

  const setupBrandingCacheInvalidation = () => {
    const enabledToggle = document.getElementById('brand_enabled');
    const form = enabledToggle?.closest('form');
    if (!enabledToggle || !form || form.dataset.brandpulseBrandCacheReady === '1') {
      return;
    }

    form.dataset.brandpulseBrandCacheReady = '1';
    form.addEventListener('submit', () => {
      try {
        cacheStorage()?.removeItem(brandingCacheKey);
      } catch (error) {
        // Storage restrictions must not prevent configuration submission.
      }
    });
  };

  const wrapSelection = (textarea, before, after = before, placeholder = '') => {
    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? textarea.value.length;
    const selected = textarea.value.slice(start, end) || placeholder;
    textarea.setRangeText(before + selected + after, start, end, 'select');
    textarea.focus();
  };

  const setupAlertFormattingToolbar = () => {
    const toolbar = document.querySelector('.brandpulse-alert-toolbar');
    const textarea = document.querySelector('.brandpulse-alert-message');
    if (!toolbar || !textarea || toolbar.dataset.ready === '1') {
      return;
    }

    toolbar.dataset.ready = '1';
    toolbar.addEventListener('click', (event) => {
      const button = event.target.closest('[data-alert-format]');
      if (!button) {
        return;
      }

      const format = button.dataset.alertFormat;
      if (format === 'heading') {
        wrapSelection(textarea, '# ', '', t('Title'));
      } else if (format === 'bold') {
        wrapSelection(textarea, '**', '**', t('important text'));
      } else if (format === 'list') {
        wrapSelection(textarea, '- ', '', t('List item'));
      } else if (format === 'code') {
        wrapSelection(textarea, '`', '`', t('Code'));
      } else if (format === 'link') {
        wrapSelection(textarea, '[', '](https://)', t('link label'));
      }
    });
  };

  const setupBrandSectionNavigation = () => {
    const openTarget = (hash) => {
      if (!hash?.startsWith('#brandpulse-brand-')) {
        return;
      }

      const section = document.getElementById(hash.slice(1));
      if (section?.matches('details.brandpulse-brand-section')) {
        section.open = true;
      }
    };

    document.querySelector('.brandpulse-brand-nav')?.addEventListener('click', (event) => {
      openTarget(event.target.closest('a[href^="#brandpulse-brand-"]')?.hash);
    });
    openTarget(window.location.hash);
  };

  const renderAssetManagementButton = (slot, payload) => {
    if (!payload?.available || !payload.preferred_url || slot.querySelector('[data-brandpulse-management-link]')) {
      return;
    }

    const container = document.createElement('div');
    container.className = 'brandpulse-asset-management btn-group';
    container.dataset.brandpulseManagementLink = '1';
    container.setAttribute('role', 'group');
    container.setAttribute('aria-label', t('Open the management interface'));

    const primary = document.createElement('a');
    primary.className = 'btn btn-primary';
    primary.href = payload.preferred_url;
    primary.target = '_blank';
    primary.rel = 'noopener noreferrer';
    primary.title = t('Open the management interface over') + ' '
      + payload.preferred_scheme.toUpperCase() + ' — ' + payload.address;
    primary.innerHTML = '<i class="ti ti-device-desktop-share" aria-hidden="true"></i> '
      + '<span>' + escapeHtml(t('Open the interface')) + '</span>';

    const fallback = document.createElement('a');
    fallback.className = 'btn btn-outline-secondary';
    fallback.href = payload.fallback_url;
    fallback.target = '_blank';
    fallback.rel = 'noopener noreferrer';
    fallback.textContent = payload.fallback_scheme.toUpperCase();
    fallback.title = t('Open over') + ' ' + payload.fallback_scheme.toUpperCase() + ' — ' + payload.address;

    container.append(primary, fallback);
    slot.append(container);
    slot.classList.add('is-available');
  };

  const setupAssetManagementLink = () => {
    const load = () => {
      for (const slot of document.querySelectorAll('[data-brandpulse-asset-management-slot]')) {
        if (slot.dataset.brandpulseManagementChecked === '1') {
          continue;
        }

        const id = Number.parseInt(slot.dataset.itemsId || '', 10);
        const itemtype = slot.dataset.itemtype || '';
        slot.dataset.brandpulseManagementChecked = '1';
        if (!Number.isInteger(id) || id <= 0 || !itemtype) {
          continue;
        }

        const query = new URLSearchParams({ itemtype, id: String(id) });
        fetch(assetManagementEndpoint + '?' + query, {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        }).then((response) => response.ok ? response.json() : null)
          .then((payload) => renderAssetManagementButton(slot, payload))
          .catch((error) => {
            window.console?.debug?.(t('BrandPulse asset management link unavailable'), error);
          });
      }
    };

    load();
    if (!assetManagementObserver) {
      let pending = false;
      assetManagementObserver = new MutationObserver((mutations) => {
        if (pending || !mutations.some((mutation) => Array.from(mutation.addedNodes).some((node) =>
          node.nodeType === 1 && (node.matches('[data-brandpulse-asset-management-slot]')
            || node.querySelector('[data-brandpulse-asset-management-slot]'))))) {
          return;
        }
        pending = true;
        window.requestAnimationFrame(() => {
          pending = false;
          load();
        });
      });
      assetManagementObserver.observe(document.body, { childList: true, subtree: true });
    }
  };

  const scheduleRefresh = (payload) => {
    if (refreshTimer) {
      window.clearTimeout(refreshTimer);
    }

    const interval = Math.max(15, Number(payload.refresh_interval || 60)) * 1000;
    const jitter = 0.9 + (Math.random() * 0.2);
    refreshTimer = window.setTimeout(loadCounters, Math.round(interval * jitter));
  };

  const requestCounterRefresh = () => {
    if (document.hidden || countersLoading) {
      freshRefreshPending = true;
      return;
    }
    // GLPI can emit both its form-success event and ajaxSuccess for one save.
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => loadCounters(true), 200);
  };

  const setupCounterRefresh = () => {
    if (counterRefreshReady) {
      return;
    }
    counterRefreshReady = true;
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        requestCounterRefresh();
      }
    });
    window.addEventListener('focus', requestCounterRefresh);
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        requestCounterRefresh();
      }
    });

    if (typeof window.jQuery === 'function') {
      window.jQuery(document).on('glpi-ajax-controller-submit-success.brandpulse', requestCounterRefresh);
      window.jQuery(document).on('ajaxSuccess.brandpulse', (event, xhr, settings) => {
        const method = String(settings.type || settings.method || 'GET').toUpperCase();
        if (method === 'GET' || method === 'HEAD') {
          return;
        }
        try {
          const url = new URL(settings.url, window.location.href);
          const appRoot = new URL(rootDoc.replace(/\/$/, '') + '/', window.location.href);
          if (url.origin === appRoot.origin && url.pathname.startsWith(appRoot.pathname)) {
            requestCounterRefresh();
          }
        } catch (error) {
          // Ignore requests outside GLPI or with an invalid URL.
        }
      });
    }
  };

  async function loadBranding() {
    try {
      const response = await fetch(brandingEndpoint, {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });

      if (response.ok) {
        const payload = await response.json();
        cacheBrandingPayload(payload);
        applyBranding(payload);
      }
    } catch (error) {
      window.console?.debug?.(t('BrandPulse branding unavailable'), error);
    }
  }

  async function loadCounters(fresh = false) {
    if (countersLoading) {
      freshRefreshPending ||= fresh;
      return;
    }
    if (document.hidden) {
      freshRefreshPending ||= fresh;
      scheduleRefresh(lastRefreshPayload);
      return;
    }
    countersLoading = true;
    fresh ||= freshRefreshPending;
    freshRefreshPending = false;
    window.clearTimeout(refreshTimer);
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(countersEndpoint + (fresh ? '?fresh=1' : ''), {
        credentials: 'same-origin',
        cache: 'no-store',
        signal: controller.signal,
        headers: {
          Accept: 'application/json',
        },
      });

      if (!response.ok) {
        return;
      }

      const payload = await response.json();
      lastRefreshPayload = payload;
      setupCompactSearch(payload.compact_search_enabled);
      render(payload);
    } catch (error) {
      window.console?.debug?.(t('BrandPulse counters unavailable'), error);
    } finally {
      window.clearTimeout(timeout);
      countersLoading = false;
      if (freshRefreshPending && !document.hidden) {
        loadCounters(true);
      } else {
        scheduleRefresh(lastRefreshPayload);
      }
    }
  }

  const boot = () => {
    if (!isHtmlDocument()) {
      return;
    }

    try {
      cacheStorage()?.removeItem('brandpulse:pulse-payload:v1');
    } catch (error) {
      // Optional browser storage must not prevent the plugin from starting.
    }

    setupPulseTargets();
    setupPulseTableControls();
    setupProfileCounterControls();
    setupProfileCounterLimit();
    setupPulseModeControls();
    setupIconPickerModal();
    setupTicketViewModal();
    setupBrandingCacheInvalidation();
    setupAlertFormattingToolbar();
    setupBrandSectionNavigation();
    setupAssetManagementLink();
    const cachedBranding = cachedBrandingPayload();
    if (cachedBranding) {
      applyBranding(cachedBranding);
    }
    loadBranding();

    if (!document.querySelector('.page-anonymous')) {
      setupCounterRefresh();
      loadCounters(true);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();

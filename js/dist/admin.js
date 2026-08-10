(function () {
  'use strict';

  var app = flarum.core.compat.app;
  var ExtensionPage = flarum.core.compat['components/ExtensionPage'];
  var saveSettings = flarum.core.compat['utils/saveSettings'];

  var PREFIX = 'markhitchk-traffic-guard.';
  var SETTING_NAMES = [
    'enabled', 'scope_forum', 'scope_api', 'scope_admin', 'api_response_mode',
    'provider', 'proxycheck_key', 'provider_timeout', 'fail_mode', 'cache_enabled', 'cache_hours',
    'block_vpn', 'block_proxy', 'block_tor', 'block_hosting', 'risk_threshold',
    'trust_proxy_headers', 'proxy_header', 'trusted_proxy_cidrs',
    'log_blocks', 'log_allowed', 'log_ip_mode', 'log_retention_days',
    'support_url', 'default_status',
    'status_manual', 'status_vpn', 'status_proxy', 'status_tor', 'status_hosting', 'status_country',
    'status_asn', 'status_path', 'status_user_agent', 'status_risk', 'status_provider_error',
    'page_default', 'page_manual', 'page_vpn', 'page_proxy', 'page_tor', 'page_hosting', 'page_country',
    'page_asn', 'page_path', 'page_user_agent', 'page_risk', 'page_provider_error'
  ];

  var PAGE_KEYS = [
    ['manual', 'Manual IP / CIDR ban'],
    ['vpn', 'VPN detected'],
    ['proxy', 'Proxy detected'],
    ['tor', 'Tor detected'],
    ['hosting', 'Hosting / datacenter'],
    ['country', 'Country rule'],
    ['asn', 'ASN rule'],
    ['path', 'Path rule'],
    ['user_agent', 'User-agent rule'],
    ['risk', 'Risk threshold'],
    ['provider_error', 'Provider error (fail-closed)'],
    ['default', 'Default fallback']
  ];

  function h(tag, attrs, children) {
    return m(tag, attrs || {}, children);
  }

  function text(value) {
    return value === null || value === undefined || value === '' ? '—' : String(value);
  }

  function formatDate(value) {
    if (!value) return 'Never';
    var date = new Date(value);
    return isNaN(date.getTime()) ? value : date.toLocaleString();
  }

  class TrafficGuardPage extends ExtensionPage {
    oninit(vnode) {
      super.oninit(vnode);

      this.activeTab = 'overview';
      this.settings = {};
      SETTING_NAMES.forEach((name) => {
        this.settings[name] = app.data.settings[PREFIX + name] !== undefined ? String(app.data.settings[PREFIX + name]) : '';
      });

      this.rules = [];
      this.logs = [];
      this.logTotal = 0;
      this.loadingRules = false;
      this.loadingLogs = false;
      this.savingSettings = false;
      this.savingRule = false;
      this.editingRuleId = null;
      this.selectedPage = 'manual';
      this.previewPage = false;
      this.logSearch = '';
      this.logAction = '';
      this.testIp = '';
      this.testPath = '/';
      this.testUserAgent = 'Traffic Guard admin test';
      this.testResult = null;
      this.testingIp = false;
      this.newRule = this.emptyRule();

      this.loadRules();
      this.loadLogs();
    }

    emptyRule() {
      return {
        type: 'ip',
        value: '',
        action: 'block',
        reason: '',
        responseKey: 'manual',
        statusCode: '',
        priority: '100',
        enabled: true,
        expiresAt: ''
      };
    }

    api(path) {
      return app.forum.attribute('apiUrl') + '/traffic-guard' + path;
    }

    isOn(name) {
      return String(this.settings[name]) === '1';
    }

    setBool(name, checked) {
      this.settings[name] = checked ? '1' : '0';
    }

    alert(type, message) {
      app.alerts.show({ type: type }, message);
    }

    requestError(error) {
      if (error && error.response) {
        if (error.response.error) return error.response.error;
        if (error.response.errors && error.response.errors[0] && error.response.errors[0].detail) return error.response.errors[0].detail;
      }
      return error && error.message ? error.message : 'The request failed.';
    }

    loadRules() {
      this.loadingRules = true;
      return app.request({ method: 'GET', url: this.api('/rules') })
        .then((data) => {
          this.rules = data.rules || [];
          this.loadingRules = false;
          m.redraw();
        })
        .catch((error) => {
          this.loadingRules = false;
          this.alert('error', this.requestError(error));
          m.redraw();
        });
    }

    loadLogs() {
      this.loadingLogs = true;
      var query = '?limit=100';
      if (this.logSearch) query += '&search=' + encodeURIComponent(this.logSearch);
      if (this.logAction) query += '&action=' + encodeURIComponent(this.logAction);

      return app.request({ method: 'GET', url: this.api('/logs') + query })
        .then((data) => {
          this.logs = data.logs || [];
          this.logTotal = data.total || 0;
          this.loadingLogs = false;
          m.redraw();
        })
        .catch((error) => {
          this.loadingLogs = false;
          this.alert('error', this.requestError(error));
          m.redraw();
        });
    }

    saveAllSettings() {
      var payload = {};
      SETTING_NAMES.forEach((name) => {
        payload[PREFIX + name] = this.settings[name] === undefined ? '' : String(this.settings[name]);
      });

      this.savingSettings = true;
      return saveSettings(payload)
        .then(() => {
          this.savingSettings = false;
          this.alert('success', 'Traffic Guard settings saved.');
          m.redraw();
        })
        .catch((error) => {
          this.savingSettings = false;
          this.alert('error', this.requestError(error));
          m.redraw();
        });
    }

    editRule(rule) {
      this.editingRuleId = rule.id;
      this.newRule = {
        type: rule.type,
        value: rule.value,
        action: rule.action,
        reason: rule.reason || '',
        responseKey: rule.responseKey || '',
        statusCode: rule.statusCode === null ? '' : String(rule.statusCode),
        priority: String(rule.priority),
        enabled: !!rule.enabled,
        expiresAt: rule.expiresAt || ''
      };
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    cancelEdit() {
      this.editingRuleId = null;
      this.newRule = this.emptyRule();
    }

    submitRule(event) {
      event.preventDefault();
      if (!this.newRule.value.trim()) {
        this.alert('error', 'Enter a value for the rule.');
        return;
      }

      var method = this.editingRuleId ? 'PATCH' : 'POST';
      var url = this.api('/rules') + (this.editingRuleId ? '/' + this.editingRuleId : '');
      var body = Object.assign({}, this.newRule);
      body.priority = parseInt(body.priority || '100', 10);
      body.statusCode = body.statusCode === '' ? null : parseInt(body.statusCode, 10);
      body.expiresAt = body.expiresAt.trim() || null;

      this.savingRule = true;
      return app.request({ method: method, url: url, body: body })
        .then(() => {
          this.savingRule = false;
          this.alert('success', this.editingRuleId ? 'Rule updated.' : 'Rule created.');
          this.cancelEdit();
          return this.loadRules();
        })
        .catch((error) => {
          this.savingRule = false;
          this.alert('error', this.requestError(error));
          m.redraw();
        });
    }

    toggleRule(rule) {
      return app.request({
        method: 'PATCH',
        url: this.api('/rules/' + rule.id),
        body: { enabled: !rule.enabled }
      }).then(() => this.loadRules()).catch((error) => this.alert('error', this.requestError(error)));
    }

    deleteRule(rule) {
      if (!confirm('Delete rule #' + rule.id + '?')) return;

      return app.request({ method: 'DELETE', url: this.api('/rules/' + rule.id) })
        .then(() => {
          this.alert('success', 'Rule deleted.');
          return this.loadRules();
        })
        .catch((error) => this.alert('error', this.requestError(error)));
    }

    clearLogs() {
      if (!confirm('Delete all Traffic Guard access logs? This cannot be undone.')) return;

      return app.request({ method: 'DELETE', url: this.api('/logs') })
        .then((data) => {
          this.alert('success', 'Deleted ' + (data.deleted || 0) + ' log entries.');
          return this.loadLogs();
        })
        .catch((error) => this.alert('error', this.requestError(error)));
    }

    purgeCache() {
      if (!confirm('Clear all cached network reputation results?')) return;

      return app.request({ method: 'DELETE', url: this.api('/cache') })
        .then((data) => this.alert('success', 'Deleted ' + (data.deleted || 0) + ' cached entries.'))
        .catch((error) => this.alert('error', this.requestError(error)));
    }

    runIpTest(event) {
      event.preventDefault();
      this.testingIp = true;
      this.testResult = null;

      return app.request({
        method: 'POST',
        url: this.api('/test-ip'),
        body: { ip: this.testIp, path: this.testPath, userAgent: this.testUserAgent }
      }).then((data) => {
        this.testingIp = false;
        this.testResult = data.result || data;
        m.redraw();
      }).catch((error) => {
        this.testingIp = false;
        this.alert('error', this.requestError(error));
        m.redraw();
      });
    }

    content() {
      return h('div', { className: 'TrafficGuardAdmin container' }, [
        this.topBar(),
        this.tabBar(),
        this.activeTab === 'overview' ? this.overviewTab() : null,
        this.activeTab === 'rules' ? this.rulesTab() : null,
        this.activeTab === 'detection' ? this.detectionTab() : null,
        this.activeTab === 'pages' ? this.pagesTab() : null,
        this.activeTab === 'logs' ? this.logsTab() : null,
        this.activeTab === 'tools' ? this.toolsTab() : null
      ]);
    }

    topBar() {
      return h('div', { className: 'tg-topbar' }, [
        h('div', {}, [
          h('h2', {}, 'Traffic Guard control panel'),
          h('p', { className: 'helpText' }, 'Block or allow traffic before the Flarum forum renders. Manual rules work without an external provider.')
        ]),
        h('button', {
          className: 'Button Button--primary',
          disabled: this.savingSettings,
          onclick: () => this.saveAllSettings()
        }, this.savingSettings ? 'Saving…' : 'Save settings')
      ]);
    }

    tabBar() {
      var tabs = [
        ['overview', 'Overview'], ['rules', 'Rules'], ['detection', 'Detection'],
        ['pages', 'Block Pages'], ['logs', 'Logs'], ['tools', 'Tools']
      ];

      return h('div', { className: 'tg-tabs' }, tabs.map((tab) => h('button', {
        className: 'Button ' + (this.activeTab === tab[0] ? 'Button--primary' : 'Button--text'),
        onclick: () => { this.activeTab = tab[0]; }
      }, tab[1])));
    }

    card(title, body, className) {
      return h('section', { className: 'tg-card ' + (className || '') }, [h('h3', {}, title), body]);
    }

    overviewTab() {
      var enabledRules = this.rules.filter((rule) => rule.enabled).length;
      var blockedLogs = this.logs.filter((log) => log.action === 'blocked').length;

      return h('div', { className: 'tg-tab' }, [
        h('div', { className: 'tg-status-banner ' + (this.isOn('enabled') ? 'is-on' : 'is-off') }, [
          h('strong', {}, this.isOn('enabled') ? 'Protection enabled' : 'Protection disabled'),
          h('span', {}, this.isOn('enabled') ? ' Traffic Guard is evaluating enabled scopes.' : ' Rules are saved but no requests are being blocked.')
        ]),
        h('div', { className: 'tg-stat-grid' }, [
          this.stat('Rules', this.rules.length, enabledRules + ' enabled'),
          this.stat('Recent blocked', blockedLogs, 'within loaded logs'),
          this.stat('Provider', this.settings.provider || 'none', this.settings.provider === 'none' ? 'manual rules only' : 'network intelligence on'),
          this.stat('API mode', this.settings.api_response_mode || 'json', 'blocked API response')
        ]),
        this.card('Master protection', h('div', { className: 'tg-form-grid' }, [
          this.checkbox('Enable Traffic Guard', 'enabled', 'Master switch. Keep this off while initially configuring rules.'),
          this.checkbox('Protect forum frontend', 'scope_forum', 'Blocks normal Flarum pages.'),
          this.checkbox('Protect API', 'scope_api', 'Blocks /api requests. Admin actions also use the API.'),
          this.checkbox('Protect admin HTML', 'scope_admin', 'Optional. Leave off until your allow rules are tested.'),
          this.selectSetting('Blocked API response', 'api_response_mode', [['json', 'JSON error'], ['html', 'Custom HTML']], 'JSON is safer for API clients; forum requests still use your HTML page.')
        ])),
        this.card('Important scope limitation', h('p', { className: 'helpText' }, 'Flarum middleware protects requests handled by Flarum. Direct static files such as /assets/* may be served by Nginx, Apache or a CDN before PHP runs. Use a web-server/CDN firewall too if you need a true whole-domain deny.'))
      ]);
    }

    stat(label, value, note) {
      return h('div', { className: 'tg-stat' }, [
        h('div', { className: 'tg-stat-label' }, label),
        h('div', { className: 'tg-stat-value' }, String(value)),
        h('div', { className: 'tg-stat-note' }, note)
      ]);
    }

    rulesTab() {
      return h('div', { className: 'tg-tab' }, [
        this.card(this.editingRuleId ? 'Edit rule #' + this.editingRuleId : 'Add rule', this.ruleForm()),
        this.card('Rules', this.ruleTable())
      ]);
    }

    ruleForm() {
      return h('form', { onsubmit: (event) => this.submitRule(event) }, [
        h('div', { className: 'tg-form-grid' }, [
          this.field('Type', h('select', {
            className: 'FormControl', value: this.newRule.type,
            onchange: (e) => {
              this.newRule.type = e.target.value;
              this.newRule.responseKey = ['ip', 'cidr'].indexOf(e.target.value) !== -1 ? 'manual' : e.target.value;
            }
          }, [
            h('option', { value: 'ip' }, 'Exact IP'),
            h('option', { value: 'cidr' }, 'CIDR range'),
            h('option', { value: 'country' }, 'Country code'),
            h('option', { value: 'asn' }, 'ASN'),
            h('option', { value: 'path' }, 'URL path glob'),
            h('option', { value: 'user_agent' }, 'User-agent contains')
          ]), 'IP and CIDR work without a reputation provider.'),
          this.field('Value', h('input', {
            className: 'FormControl', value: this.newRule.value,
            placeholder: this.rulePlaceholder(),
            oninput: (e) => { this.newRule.value = e.target.value; }
          }), 'Examples: 203.0.113.5, 203.0.113.0/24, US, AS13335, /admin*'),
          this.field('Action', h('select', {
            className: 'FormControl', value: this.newRule.action,
            onchange: (e) => { this.newRule.action = e.target.value; }
          }, [h('option', { value: 'block' }, 'Block'), h('option', { value: 'allow' }, 'Allow / bypass')]), 'Matching allow rules override blocking checks.'),
          this.field('Priority', h('input', {
            className: 'FormControl', type: 'number', value: this.newRule.priority,
            oninput: (e) => { this.newRule.priority = e.target.value; }
          }), 'Higher priorities are evaluated first within the same action.'),
          this.field('Response page', h('select', {
            className: 'FormControl', value: this.newRule.responseKey,
            onchange: (e) => { this.newRule.responseKey = e.target.value; }
          }, [h('option', { value: '' }, 'Automatic for rule type')].concat(PAGE_KEYS.map((p) => h('option', { value: p[0] }, p[1])))), 'Only applies to block rules.'),
          this.field('HTTP status override', h('input', {
            className: 'FormControl', type: 'number', min: '400', max: '599', value: this.newRule.statusCode,
            placeholder: 'Use page default', oninput: (e) => { this.newRule.statusCode = e.target.value; }
          }), '403 is recommended. 404 can hide that the resource exists.'),
          this.field('Expires at', h('input', {
            className: 'FormControl', value: this.newRule.expiresAt,
            placeholder: '2026-08-31T23:59:00Z', oninput: (e) => { this.newRule.expiresAt = e.target.value; }
          }), 'Leave blank for permanent. ISO 8601 is recommended.'),
          this.field('Reason', h('textarea', {
            className: 'FormControl', rows: 3, value: this.newRule.reason,
            oninput: (e) => { this.newRule.reason = e.target.value; }
          }), 'Can be inserted into block HTML with {{REASON}}.'),
          this.field('Enabled', h('label', { className: 'checkbox' }, [
            h('input', { type: 'checkbox', checked: this.newRule.enabled, onchange: (e) => { this.newRule.enabled = e.target.checked; } }),
            ' Active immediately after saving'
          ]))
        ]),
        h('div', { className: 'tg-actions' }, [
          h('button', { className: 'Button Button--primary', type: 'submit', disabled: this.savingRule }, this.savingRule ? 'Saving…' : (this.editingRuleId ? 'Update rule' : 'Add rule')),
          this.editingRuleId ? h('button', { className: 'Button', type: 'button', onclick: () => this.cancelEdit() }, 'Cancel') : null
        ])
      ]);
    }

    rulePlaceholder() {
      var map = {
        ip: '203.0.113.5', cidr: '203.0.113.0/24', country: 'US', asn: 'AS13335',
        path: '/private/*', user_agent: 'BadBot'
      };
      return map[this.newRule.type] || '';
    }

    ruleTable() {
      if (this.loadingRules) return h('p', { className: 'helpText' }, 'Loading rules…');
      if (!this.rules.length) return h('p', { className: 'helpText' }, 'No rules yet. Add an allow or block rule above.');

      return h('div', { className: 'tg-table-wrap' }, h('table', { className: 'tg-table' }, [
        h('thead', {}, h('tr', {}, ['ID', 'Type', 'Value', 'Action', 'Priority', 'Expires', 'Page', 'Status', 'Enabled', 'Actions'].map((v) => h('th', {}, v)))),
        h('tbody', {}, this.rules.map((rule) => h('tr', { className: rule.enabled ? '' : 'is-disabled' }, [
          h('td', {}, '#' + rule.id),
          h('td', {}, rule.type),
          h('td', { className: 'tg-mono' }, rule.value),
          h('td', {}, h('span', { className: 'tg-pill ' + (rule.action === 'allow' ? 'allow' : 'block') }, rule.action)),
          h('td', {}, rule.priority),
          h('td', {}, formatDate(rule.expiresAt)),
          h('td', {}, text(rule.responseKey)),
          h('td', {}, text(rule.statusCode)),
          h('td', {}, rule.enabled ? 'Yes' : 'No'),
          h('td', { className: 'tg-row-actions' }, [
            h('button', { className: 'Button Button--small', onclick: () => this.editRule(rule) }, 'Edit'),
            h('button', { className: 'Button Button--small', onclick: () => this.toggleRule(rule) }, rule.enabled ? 'Disable' : 'Enable'),
            h('button', { className: 'Button Button--small Button--danger', onclick: () => this.deleteRule(rule) }, 'Delete')
          ])
        ])))
      ]));
    }

    detectionTab() {
      return h('div', { className: 'tg-tab' }, [
        this.card('Network reputation provider', h('div', { className: 'tg-form-grid' }, [
          this.selectSetting('Provider', 'provider', [['none', 'None (manual rules only)'], ['proxycheck', 'proxycheck.io v2']], 'Provider lookups happen on the backend and are cached.'),
          this.passwordSetting('ProxyCheck API key', 'proxycheck_key', 'Optional depending on your ProxyCheck plan; it never goes to forum visitors.'),
          this.numberSetting('Provider timeout (seconds)', 'provider_timeout', 1, 10, 'Short timeout reduces impact if the provider is unavailable.'),
          this.selectSetting('Provider failure mode', 'fail_mode', [['open', 'Fail open (allow)'], ['closed', 'Fail closed (block)']], 'Fail open is strongly recommended for availability.'),
          this.checkbox('Cache lookup results', 'cache_enabled', 'Avoids an external request on every page load.'),
          this.numberSetting('Cache duration (hours)', 'cache_hours', 1, 720, '24 hours is a reasonable default.')
        ])),
        this.card('Automatic detection rules', h('div', { className: 'tg-form-grid' }, [
          this.checkbox('Block VPN', 'block_vpn', 'Uses the VPN block page.'),
          this.checkbox('Block proxy', 'block_proxy', 'Uses the proxy block page.'),
          this.checkbox('Block Tor', 'block_tor', 'Uses the Tor block page.'),
          this.checkbox('Block hosting/datacenter', 'block_hosting', 'This has a higher false-positive risk; leave off unless needed.'),
          this.numberSetting('Risk score threshold', 'risk_threshold', 0, 100, '0 disables risk-score blocking. Example: 85.')
        ])),
        this.card('Reverse proxy / CDN client IP', h('div', { className: 'tg-form-grid' }, [
          this.checkbox('Trust a forwarded client-IP header', 'trust_proxy_headers', 'Only enable when Flarum is behind a known reverse proxy/CDN.'),
          this.selectSetting('Client IP header', 'proxy_header', [['CF-Connecting-IP', 'CF-Connecting-IP'], ['X-Forwarded-For', 'X-Forwarded-For'], ['X-Real-IP', 'X-Real-IP']], 'The immediate REMOTE_ADDR must match a trusted proxy range before this header is accepted.'),
          this.field('Trusted proxy IPs / CIDRs', h('textarea', {
            className: 'FormControl', rows: 6, value: this.settings.trusted_proxy_cidrs,
            placeholder: 'One IP or CIDR per line', oninput: (e) => { this.settings.trusted_proxy_cidrs = e.target.value; }
          }), 'Never enable forwarded headers without trusted proxy ranges, or clients may spoof their IP.')
        ]))
      ]);
    }

    pagesTab() {
      var pageName = 'page_' + this.selectedPage;
      var statusName = this.selectedPage === 'default' ? 'default_status' : 'status_' + this.selectedPage;

      return h('div', { className: 'tg-tab' }, [
        this.card('Custom block response', h('div', {}, [
          h('div', { className: 'tg-form-grid' }, [
            this.field('Block reason', h('select', {
              className: 'FormControl', value: this.selectedPage,
              onchange: (e) => { this.selectedPage = e.target.value; this.previewPage = false; }
            }, PAGE_KEYS.map((p) => h('option', { value: p[0] }, p[1])))),
            this.field('HTTP status', h('input', {
              className: 'FormControl', type: 'number', min: '400', max: '599', value: this.settings[statusName],
              oninput: (e) => { this.settings[statusName] = e.target.value; }
            }), '403 is standard. You can use 404 for a custom not-found style page.')
          ]),
          this.field('Full HTML document', h('textarea', {
            className: 'FormControl tg-code-editor', rows: 24, spellcheck: 'false', value: this.settings[pageName],
            oninput: (e) => { this.settings[pageName] = e.target.value; }
          }), 'This HTML is trusted administrator code and is returned directly to blocked visitors. Dynamic values are HTML-escaped before insertion.'),
          h('div', { className: 'tg-actions' }, [
            h('button', { className: 'Button', type: 'button', onclick: () => { this.previewPage = !this.previewPage; } }, this.previewPage ? 'Hide preview' : 'Preview safely'),
            h('button', { className: 'Button Button--primary', type: 'button', onclick: () => this.saveAllSettings() }, 'Save settings')
          ]),
          this.previewPage ? h('iframe', { className: 'tg-preview', sandbox: '', srcdoc: this.previewHtml(this.settings[pageName], this.settings[statusName]) }) : null
        ])),
        this.card('Template variables', h('div', { className: 'tg-token-grid' }, [
          '{{IP}}', '{{STATUS}}', '{{REASON}}', '{{BLOCK_ID}}', '{{BLOCK_TYPE}}', '{{RULE_ID}}', '{{EXPIRES}}',
          '{{DATE_UTC}}', '{{PATH}}', '{{COUNTRY}}', '{{COUNTRY_CODE}}', '{{ASN}}', '{{RISK}}', '{{FORUM_NAME}}', '{{FORUM_URL}}', '{{SUPPORT_URL}}'
        ].map((token) => h('code', {}, token))))
      ]);
    }

    previewHtml(html, status) {
      var replacements = {
        '{{IP}}': '203.0.113.25', '{{STATUS}}': status || '403', '{{REASON}}': 'Example Traffic Guard restriction.',
        '{{BLOCK_ID}}': 'TG-PREVIEW00001', '{{BLOCK_TYPE}}': this.selectedPage.toUpperCase(), '{{RULE_ID}}': '42',
        '{{EXPIRES}}': 'Permanent / not specified', '{{DATE_UTC}}': new Date().toISOString(), '{{PATH}}': '/example',
        '{{COUNTRY}}': 'Example Country', '{{COUNTRY_CODE}}': 'US', '{{ASN}}': 'AS64500', '{{RISK}}': '85',
        '{{FORUM_NAME}}': app.data.settings.forum_title || 'Flarum', '{{FORUM_URL}}': window.location.origin, '{{SUPPORT_URL}}': this.settings.support_url || ''
      };
      var output = html || '<h1>No HTML configured</h1>';
      Object.keys(replacements).forEach((key) => { output = output.split(key).join(replacements[key]); });
      return output;
    }

    logsTab() {
      return h('div', { className: 'tg-tab' }, [
        this.card('Logging settings', h('div', { className: 'tg-form-grid' }, [
          this.checkbox('Log blocked requests', 'log_blocks', 'Recommended for troubleshooting.'),
          this.checkbox('Log allowed requests', 'log_allowed', 'Can create a large amount of data.'),
          this.selectSetting('IP storage in logs', 'log_ip_mode', [['full', 'Full IP'], ['masked', 'Masked /24 or /64'], ['hashed', 'SHA-256 hash']], 'Rules still require real IPs; this setting affects logs only.'),
          this.numberSetting('Log retention (days)', 'log_retention_days', 1, 3650, 'The scheduled prune command removes older logs.'),
          this.textSetting('Support URL', 'support_url', 'Optional URL exposed through {{SUPPORT_URL}}.')
        ])),
        this.card('Access log', h('div', {}, [
          h('div', { className: 'tg-log-controls' }, [
            h('input', { className: 'FormControl', value: this.logSearch, placeholder: 'Search IP, path, reason…', oninput: (e) => { this.logSearch = e.target.value; } }),
            h('select', { className: 'FormControl', value: this.logAction, onchange: (e) => { this.logAction = e.target.value; } }, [
              h('option', { value: '' }, 'All actions'), h('option', { value: 'blocked' }, 'Blocked'), h('option', { value: 'allowed' }, 'Allowed')
            ]),
            h('button', { className: 'Button', onclick: () => this.loadLogs() }, 'Refresh'),
            h('button', { className: 'Button Button--danger', onclick: () => this.clearLogs() }, 'Clear logs')
          ]),
          h('p', { className: 'helpText' }, 'Showing up to 100 entries. Matching total: ' + this.logTotal + '.'),
          this.logTable()
        ]))
      ]);
    }

    logTable() {
      if (this.loadingLogs) return h('p', { className: 'helpText' }, 'Loading logs…');
      if (!this.logs.length) return h('p', { className: 'helpText' }, 'No matching log entries.');

      return h('div', { className: 'tg-table-wrap' }, h('table', { className: 'tg-table' }, [
        h('thead', {}, h('tr', {}, ['Time', 'Action', 'IP', 'Category', 'Rule', 'Path', 'Reason', 'Details'].map((v) => h('th', {}, v)))),
        h('tbody', {}, this.logs.map((log) => h('tr', {}, [
          h('td', {}, formatDate(log.createdAt)),
          h('td', {}, h('span', { className: 'tg-pill ' + (log.action === 'allowed' ? 'allow' : 'block') }, log.action)),
          h('td', { className: 'tg-mono' }, text(log.ip)),
          h('td', {}, text(log.category)),
          h('td', {}, log.ruleId ? '#' + log.ruleId : '—'),
          h('td', { className: 'tg-mono' }, text(log.path)),
          h('td', {}, text(log.reason)),
          h('td', {}, h('details', {}, [h('summary', {}, 'Metadata'), h('pre', { className: 'tg-json' }, JSON.stringify(log.metadata || {}, null, 2))]))
        ])))
      ]));
    }

    toolsTab() {
      return h('div', { className: 'tg-tab' }, [
        this.card('Test an IP', h('form', { onsubmit: (event) => this.runIpTest(event) }, [
          h('p', { className: 'helpText' }, 'Evaluates a supplied address without blocking your current browser. Threat-provider lookup is forced for the test when a provider is configured.'),
          h('div', { className: 'tg-form-grid' }, [
            this.field('IP address', h('input', { className: 'FormControl', required: true, value: this.testIp, placeholder: '8.8.8.8', oninput: (e) => { this.testIp = e.target.value; } })),
            this.field('Path', h('input', { className: 'FormControl', value: this.testPath, oninput: (e) => { this.testPath = e.target.value; } })),
            this.field('User-Agent', h('input', { className: 'FormControl', value: this.testUserAgent, oninput: (e) => { this.testUserAgent = e.target.value; } }))
          ]),
          h('button', { className: 'Button Button--primary', type: 'submit', disabled: this.testingIp }, this.testingIp ? 'Testing…' : 'Run test'),
          this.testResult ? h('pre', { className: 'tg-json tg-test-result' }, JSON.stringify(this.testResult, null, 2)) : null
        ])),
        this.card('Maintenance', h('div', { className: 'tg-actions' }, [
          h('button', { className: 'Button', onclick: () => this.purgeCache() }, 'Clear threat cache')
        ])),
        this.card('Emergency CLI recovery', h('div', {}, [
          h('p', { className: 'helpText' }, 'If you accidentally lock your own network out, use your server shell. These commands do not require access to the admin page.'),
          h('pre', { className: 'tg-json' }, 'php flarum traffic-guard:disable\nphp flarum traffic-guard:unban YOUR.IP.ADDRESS\nphp flarum traffic-guard:status\nphp flarum traffic-guard:enable')
        ]))
      ]);
    }

    checkbox(label, name, help) {
      return this.field(label, h('label', { className: 'checkbox' }, [
        h('input', { type: 'checkbox', checked: this.isOn(name), onchange: (e) => this.setBool(name, e.target.checked) }),
        ' ' + label
      ]), help);
    }

    textSetting(label, name, help) {
      return this.field(label, h('input', { className: 'FormControl', value: this.settings[name], oninput: (e) => { this.settings[name] = e.target.value; } }), help);
    }

    passwordSetting(label, name, help) {
      return this.field(label, h('input', { className: 'FormControl', type: 'password', autocomplete: 'new-password', value: this.settings[name], oninput: (e) => { this.settings[name] = e.target.value; } }), help);
    }

    numberSetting(label, name, min, max, help) {
      return this.field(label, h('input', { className: 'FormControl', type: 'number', min: String(min), max: String(max), value: this.settings[name], oninput: (e) => { this.settings[name] = e.target.value; } }), help);
    }

    selectSetting(label, name, options, help) {
      return this.field(label, h('select', { className: 'FormControl', value: this.settings[name], onchange: (e) => { this.settings[name] = e.target.value; } }, options.map((option) => h('option', { value: option[0] }, option[1]))), help);
    }

    field(label, control, help) {
      return h('div', { className: 'Form-group tg-field' }, [
        h('label', {}, label),
        control,
        help ? h('div', { className: 'helpText' }, help) : null
      ]);
    }
  }

  app.initializers.add('markhitchk-traffic-guard', function () {
    app.extensionData.for('markhitchk-traffic-guard').registerPage(TrafficGuardPage);
  });
})();

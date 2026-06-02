((Drupal, once, drupalSettings) => {
  Drupal.behaviors.k7zzLdapTest = {
    attach(context) {
      once('k7zz-ldap-test', '[data-ldap-test-btn]', context).forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();

          const testUrl   = drupalSettings?.k7zzLdapTest?.testUrl;
          const resultDiv = document.getElementById('ldap-connection-test-result');

          if (!testUrl) return;
          if (resultDiv) resultDiv.innerHTML = '<em>Teste Verbindung…</em>';

          // Feldwerte aus dem Formular lesen — Namen enden auf [feldname]
          const get = (name) => {
            const el = document.querySelector(
              `[name$="[${name}]"], [name="${name}"]`
            );
            return el ? el.value : '';
          };

          const body = new FormData();
          body.append('ldap_host',          get('ldap_host'));
          body.append('ldap_port',          get('ldap_port') || '389');
          body.append('ldap_encryption',    get('ldap_encryption') || 'none');
          body.append('ldap_bind_dn',       get('ldap_bind_dn'));
          body.append('ldap_bind_pw',       get('ldap_bind_pw'));
          body.append('ldap_base_dn',       get('ldap_base_dn'));
          body.append('ldap_search_filter', get('ldap_search_filter'));
          body.append('ldap_attribute',     get('ldap_attribute'));

          fetch(testUrl, {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          })
            .then((r) => r.json())
            .then((data) => {
              if (!resultDiv) return;
              const cls  = data.success ? 'messages--status' : 'messages--error';
              const icon = data.success ? '✅' : '❌';
              resultDiv.innerHTML = `<div class="messages ${cls}">${icon} ${data.message}</div>`;
            })
            .catch(() => {
              if (resultDiv) {
                resultDiv.innerHTML =
                  '<div class="messages messages--error">❌ Verbindungstest fehlgeschlagen.</div>';
              }
            });
        });
      });
    },
  };
})(Drupal, once, drupalSettings);

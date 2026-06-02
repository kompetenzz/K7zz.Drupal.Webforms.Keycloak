<?php

namespace Drupal\k7zz_webform_keycloak\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\k7zz_ldap_lib\Client as LdapClient;

/**
 * Testet eine LDAP-Verbindung mit den per POST übermittelten Zugangsdaten.
 * Wird vom „Verbindung testen"-Button im Keycloak-Handler-Formular aufgerufen.
 */
class LdapTestController extends ControllerBase
{
    public function test(Request $request): JsonResponse
    {
        $host       = trim($request->request->get('ldap_host', ''));
        $port       = (int) $request->request->get('ldap_port', 389);
        $encryption = $request->request->get('ldap_encryption', 'none');
        $bindDn     = trim($request->request->get('ldap_bind_dn', ''));
        $bindPw     = $request->request->get('ldap_bind_pw', '');

        if ($host === '' || $bindDn === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Host und Bind-DN sind erforderlich.',
            ]);
        }

        $baseDn       = trim($request->request->get('ldap_base_dn', ''));
        $searchFilter = trim($request->request->get('ldap_search_filter', ''));
        $attribute    = trim($request->request->get('ldap_attribute', ''));

        try {
            $ldap = new LdapClient(
                host:       $host,
                port:       $port,
                encryption: $encryption,
                bindDn:     $bindDn,
                bindPw:     $bindPw,
            );

            if ($ldap->bind()) {
                $message = "Verbindung zu {$host}:{$port} erfolgreich.";

                // Nächste Inkrement-Nummer ermitteln, falls Base DN und Attribut gesetzt
                if ($baseDn !== '' && $attribute !== '') {
                    try {
                        $next     = $ldap->getNextAttributeValue($attribute, $baseDn, $searchFilter);
                        $message .= " Nächste {$attribute}: <strong>{$next}</strong>";
                    } catch (\Throwable $e) {
                        $message .= " (Inkrement-Abfrage fehlgeschlagen: {$e->getMessage()})";
                    }
                }

                return new JsonResponse(['success' => true, 'message' => $message]);
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Bind fehlgeschlagen — Credentials oder Verschlüsselung prüfen.',
            ]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace Drupal\k7zz_webform_keycloak\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\k7zz_keycloak_lib\Client;
use Drupal\k7zz_ldap_lib\Client as LdapClient;

/**
 * Keycloak Account Creator Webform handler.
 *
 * @WebformHandler(
 *   id = "keycloak_account_create",
 *   label = @Translation("Keycloak Account Creation"),
 *   category = @Translation("External service"),
 *   description = @Translation("Provision users to Keycloak on submit."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class KeycloakAccountCreateHandler extends KeycloakHandlerBase
{
    private $fields = [
        'username'  => 'Username (only needed if Keycloak lookup field is Username)',
        'email'     => 'E-Mail',
        'password'  => 'Password',
        'firstname' => 'First name',
        'lastname'  => 'Last name',
        'title'     => 'Title',
        'photo'     => 'Photo Upload (public)',
    ];

    private $required_fields = [
        'email',
        'password',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Konfiguration
    // ─────────────────────────────────────────────────────────────────────────

    public function defaultConfiguration(): array
    {
        $config = parent::defaultConfiguration();
        $config += [
            // Gruppen
            'group_field'          => '',
            'use_group_from_field' => FALSE,
            'group_separator'      => ',',
            'group_name'           => '',
            'field_map'            => [],
            // LDAP-Autoincrement
            'ldap_enabled'         => FALSE,
            'ldap_host'            => '',
            'ldap_port'            => '389',
            'ldap_encryption'      => 'none',
            'ldap_bind_dn'         => '',
            'ldap_bind_pw'         => '',
            'ldap_base_dn'         => '',
            'ldap_search_filter'   => '',
            'ldap_attribute'       => 'employeeNumber',
        ];
        foreach (array_keys($this->fields) as $field) {
            $config['field_map'][$field] = '';
        }
        return $config;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Konfigurationsformular
    // ─────────────────────────────────────────────────────────────────────────

    public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
    {
        $form = parent::buildKeycloakSettingsForm($form, $form_state);

        // ── Keycloak-Account-Einstellungen ────────────────────────────────────
        $form['keycloak_account'] = [
            '#type'       => 'fieldset',
            '#title'      => $this->t('Keycloak account settings'),
            '#attributes' => ['id' => 'webform-keycloak-account-handler-settings'],
        ];

        $form['keycloak_account']['use_group_from_field'] = [
            '#type'          => 'checkbox',
            '#title'         => $this->t('Use group from Webform field?'),
            '#default_value' => $this->configuration['use_group_from_field'],
        ];
        $form['keycloak_account']['group_field'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Webform field for group name'),
            '#default_value' => $this->configuration['group_field'],
            '#states'        => [
                'visible' => [':input[name="use_group_from_field"]' => ['checked' => TRUE]],
            ],
        ];
        $form['keycloak_account']['group_name'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Static group name (may be combined with field)'),
            '#default_value' => $this->configuration['group_name'],
            '#states'        => [
                'visible' => [':input[name="use_group_from_field"]' => ['checked' => FALSE]],
            ],
        ];
        $form['keycloak_account']['group_separator'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Group value separator (for multi-value field, e.g. comma)'),
            '#default_value' => $this->configuration['group_separator'] ?? ',',
            '#required'      => TRUE,
        ];

        $form['keycloak_account']['field_map'] = [
            '#type'  => 'fieldset',
            '#title' => $this->t('Field mapping'),
        ];
        foreach ($this->fields as $field => $label) {
            $form['keycloak_account']['field_map'][$field] = [
                '#type'          => 'textfield',
                '#title'         => $this->t($label),
                '#default_value' => $this->configuration['field_map'][$field],
                '#description'   => $this->t('Machine name of the Webform field.'),
                '#required'      => in_array($field, $this->required_fields),
            ];
        }

        // ── LDAP-Autoincrement ────────────────────────────────────────────────
        $form['ldap_autoincrement'] = [
            '#type'        => 'fieldset',
            '#title'       => $this->t('LDAP Autoincrement'),
            '#description' => $this->t(
                'Liest den höchsten Wert eines Attributs direkt aus dem LDAP, '
                . 'inkrementiert ihn und trägt ihn beim Anlegen des Keycloak-Kontos ein.'
            ),
        ];

        $form['ldap_autoincrement']['ldap_enabled'] = [
            '#type'          => 'checkbox',
            '#title'         => $this->t('LDAP-Autoincrement aktivieren'),
            '#default_value' => $this->configuration['ldap_enabled'],
        ];

        // Alle weiteren Felder nur sichtbar wenn aktiviert
        $ldapVisible = ['visible' => [':input[name="ldap_enabled"]' => ['checked' => TRUE]]];

        $form['ldap_autoincrement']['ldap_host'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('LDAP Host'),
            '#default_value' => $this->configuration['ldap_host'],
            '#description'   => $this->t('z.B. ldap.example.org'),
            '#states'        => $ldapVisible,
        ];
        $form['ldap_autoincrement']['ldap_port'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Port'),
            '#default_value' => $this->configuration['ldap_port'],
            '#size'          => 6,
            '#description'   => $this->t('Standard: 389 (LDAP) oder 636 (LDAPS)'),
            '#states'        => $ldapVisible,
        ];
        $form['ldap_autoincrement']['ldap_encryption'] = [
            '#type'          => 'select',
            '#title'         => $this->t('Verschlüsselung'),
            '#options'       => [
                'none' => $this->t('Keine (plaintext)'),
                'tls'  => $this->t('STARTTLS'),
                'ssl'  => $this->t('SSL/LDAPS'),
            ],
            '#default_value' => $this->configuration['ldap_encryption'],
            '#states'        => $ldapVisible,
        ];
        $form['ldap_autoincrement']['ldap_bind_dn'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Bind DN (Service Account)'),
            '#default_value' => $this->configuration['ldap_bind_dn'],
            '#description'   => $this->t('z.B. cn=svc-drupal,ou=services,dc=example,dc=org'),
            '#states'        => $ldapVisible,
        ];
        $form['ldap_autoincrement']['ldap_bind_pw'] = [
            '#type'          => 'textfield', // password würde den Wert nicht speichern
            '#title'         => $this->t('Bind Passwort'),
            '#default_value' => $this->configuration['ldap_bind_pw'],
            '#states'        => $ldapVisible,
        ];
        $form['ldap_autoincrement']['ldap_base_dn'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('Search Base DN'),
            '#default_value' => $this->configuration['ldap_base_dn'],
            '#description'   => $this->t('z.B. ou=users,dc=example,dc=org'),
            '#states'        => $ldapVisible,
        ];
        $form['ldap_autoincrement']['ldap_search_filter'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('LDAP-Filter (optional)'),
            '#default_value' => $this->configuration['ldap_search_filter'],
            '#description'   => $this->t(
                'Schränkt die Suche ein. Leer lassen = automatisch <code>({attribut}=*)</code>. '
                . 'Beispiel: <code>(objectClass=inetOrgPerson)</code>'
            ),
            '#states'        => $ldapVisible,
        ];
        $form['ldap_autoincrement']['ldap_attribute'] = [
            '#type'          => 'textfield',
            '#title'         => $this->t('LDAP-Attribut'),
            '#default_value' => $this->configuration['ldap_attribute'],
            '#description'   => $this->t(
                'Name des LDAP-Attributs, dessen Maximum gelesen und inkrementiert wird. '
                . 'Derselbe Name wird als Keycloak-User-Attribut gesetzt.'
            ),
            '#states'        => $ldapVisible,
        ];

        return $form;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formular speichern
    // ─────────────────────────────────────────────────────────────────────────

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
    {
        parent::submitConfigurationForm($form, $form_state);
        $values = $form_state->getValues();

        // keycloak_account-Werte (inkl. field_map)
        foreach ($this->configuration as $name => $value) {
            if (array_key_exists($name, $values['keycloak_account'] ?? [])) {
                $this->configuration[$name] = $values['keycloak_account'][$name];
            }
        }

        // ldap_autoincrement-Werte
        foreach ($this->configuration as $name => $value) {
            if (array_key_exists($name, $values['ldap_autoincrement'] ?? [])) {
                $this->configuration[$name] = $values['ldap_autoincrement'][$name];
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Submission
    // ─────────────────────────────────────────────────────────────────────────

    protected function getConcreteFieldName(string $mapName): string
    {
        return $this->fields[$mapName] ?? $mapName;
    }

    public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void
    {
        if ($webform_submission->isDraft()) {
            \Drupal::logger('k7zz_webform_keycloak')
                ->notice('Not submitting draft to keycloak for account creation.');
            parent::postSave($webform_submission, $update);
            return;
        }

        \Drupal::logger('k7zz_webform_keycloak')
            ->notice('Submitting to keycloak to create an account.');

        $config = $this->configuration;
        $data   = $webform_submission->getData();

        // Werte sammeln laut Mapping
        $user_data = [];
        foreach (array_keys($this->fields) as $field) {
            $user_data[$field] = $data[$config['field_map'][$field]] ?? null;
        }

        // Pflichtfelder prüfen
        foreach ($this->required_fields as $field) {
            if (empty($user_data[$field])) {
                \Drupal::logger('k7zz_webform_keycloak')->warning(
                    'Missing required field: @field',
                    ['@field' => $this->getConcreteFieldName($field)]
                );
                \Drupal::messenger()->addError(
                    '❌ ' . $this->t('@field is required.', ['@field' => $this->getConcreteFieldName($field)])
                );
            }
        }

        // Bild-URL aus Upload-Feld holen
        $photoUrl = null;
        if ($user_data['photo'] && $user_data['photo'] > 0) {
            $file = \Drupal\file\Entity\File::load($user_data['photo']);
            if ($file) {
                $photoUrl = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
            }
        }

        // User-Objekt vorbereiten
        $kcUser = [
            'email'    => $user_data['email'],
            'password' => $user_data['password'],
        ];
        if ($user_data['uid'])       $kcUser['uid']       = $user_data['uid'];
        if ($user_data['firstname']) $kcUser['firstname'] = $user_data['firstname'];
        if ($user_data['lastname'])  $kcUser['lastname']  = $user_data['lastname'];
        if ($user_data['title'])     $kcUser['title']     = $user_data['title'];
        if ($photoUrl !== null)       $kcUser['photo']     = $photoUrl;

        // ── LDAP-Autoincrement ────────────────────────────────────────────────
        if (!empty($config['ldap_enabled']) && !empty($config['ldap_attribute'])) {
            $ldapAttr = trim($config['ldap_attribute']);
            try {
                $ldap = new LdapClient(
                    host:       $config['ldap_host'],
                    port:       (int) ($config['ldap_port'] ?? 389),
                    encryption: $config['ldap_encryption'] ?? 'none',
                    bindDn:     $config['ldap_bind_dn'],
                    bindPw:     $config['ldap_bind_pw'],
                );
                $nextId = $ldap->getNextAttributeValue(
                    attrName: $ldapAttr,
                    baseDn:   $config['ldap_base_dn'],
                    filter:   $config['ldap_search_filter'] ?? '',
                );
                // Als Keycloak-Attribut setzen (Keycloak speichert Attribute als Arrays)
                $kcUser['attributes'][$ldapAttr] = [$nextId];
                \Drupal::logger('k7zz_webform_keycloak')->notice(
                    'LDAP autoincrement: @attr → @val',
                    ['@attr' => $ldapAttr, '@val' => $nextId]
                );
            } catch (\Throwable $e) {
                \Drupal::logger('k7zz_webform_keycloak')->error(
                    'LDAP autoincrement failed: @msg',
                    ['@msg' => $e->getMessage()]
                );
                \Drupal::messenger()->addWarning(
                    '⚠️ ' . $this->t('LDAP-Autoincrement fehlgeschlagen — Konto wird ohne @attr angelegt.', [
                        '@attr' => $ldapAttr,
                    ])
                );
            }
        }

        // ── Keycloak-User anlegen ─────────────────────────────────────────────
        $uuid = $this->getConnector()->createUser($kcUser);
        $err  = is_int($uuid) && (int) $uuid < 0;
        if ($err) {
            switch ((int) $uuid) {
                case Client::ERR_USER_EXISTS:
                    \Drupal::messenger()->addError('⚠️ Konto existiert bereits.');
                    break;
                case Client::ERR_EMAIL_INVALID:
                    \Drupal::messenger()->addError('❌ E-Mail-Adresse ungültig.');
                    break;
                case Client::ERR_CREATE_FAILED:
                    \Drupal::messenger()->addError('❌ Konto konnte nicht angelegt werden.');
                    break;
                default:
                    \Drupal::messenger()->addError('❌ Unbekannter Fehler beim Konto-Setup.');
                    break;
            }
            return;
        }

        \Drupal::logger('k7zz_webform_keycloak')
            ->notice('User created in Keycloak: @user', ['@user' => $user_data['email']]);
        \Drupal::messenger()->addStatus('✅ Konto erfolgreich erstellt.');

        // ── Gruppenbehandlung ─────────────────────────────────────────────────
        $groups = [];
        if (!empty($config['use_group_from_field']) && !empty($config['group_field'])) {
            $groups = explode($config['group_separator'], $data[$config['group_field']]);
        }
        if (!empty($config['group_name'])) {
            $groups = array_merge($groups, explode($config['group_separator'], $config['group_name']));
        }

        $postSave = count($groups) < 1;
        if (!$postSave) {
            foreach ($groups as $group) {
                $group = trim($group);
                if (empty($group)) continue;
                $ugid = $this->getConnector()->findGroupIdByName($group);
                if ($ugid) {
                    if ($this->getConnector()->assignUserToGroup($uuid, $ugid)) {
                        $postSave = true;
                        \Drupal::logger('k7zz_webform_keycloak')
                            ->notice('User assigned to group: @group', ['@group' => $group]);
                    }
                } else {
                    \Drupal::logger('k7zz_webform_keycloak')
                        ->warning('Group not found: @group', ['@group' => $group]);
                }
            }
        }
        if ($postSave) {
            parent::postSave($webform_submission, $update);
        }
    }
}

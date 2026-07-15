<?php

namespace Drupal\k7zz_webform_keycloak\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\k7zz_keycloak_lib\Client;

abstract class KeycloakHandlerBase extends WebformHandlerBase
{

    /** @var Client|null */
    protected $connector;

    protected function getConnector(): ?Client
    {
        if (!$this->connector) {
            $config = $this->configuration;
            $this->connector = new Client(
                $config['base_url'],
                $config['realm'],
                $config['client_id'],
                $config['client_secret'],
                $config['identifier_field_type'] ?? 'email'
            );
        }
        return $this->connector;
    }

    protected function getUserId(string $identifier): ?string
    {
        return $this->getConnector()?->findUserId($identifier);
    }

    public function defaultConfiguration(): array
    {
        return [
            'base_url' => '',
            'realm' => '',
            'client_id' => '',
            'client_secret' => '',
            'identifier_field_type' => 'email',
            'identifier_field' => '',
            'success_status_field' => '',
        ];
    }

    protected function buildKeycloakSettingsForm(array $form, FormStateInterface $form_state): array
    {
        $form['keycloak'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Keycloak settings'),
            '#attributes' => ['id' => 'webform-keycloak-handler-settings'],
        ];

        $form['keycloak']['base_url'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Keycloak Base URL'),
            '#default_value' => $this->configuration['base_url'],
            '#required' => TRUE,
        ];
        $form['keycloak']['realm'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Realm'),
            '#default_value' => $this->configuration['realm'],
            '#required' => TRUE,
        ];
        $form['keycloak']['client_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Client ID'),
            '#default_value' => $this->configuration['client_id'],
            '#required' => TRUE,
        ];
        $form['keycloak']['client_secret'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Client Secret'),
            '#default_value' => $this->configuration['client_secret'],
            '#required' => TRUE,
        ];
        $form['keycloak']['identifier_field_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Keycloak lookup field'),
            '#options' => ['email' => 'Email', 'username' => 'Username'],
            '#default_value' => $this->configuration['identifier_field_type'],
        ];
        $form['keycloak']['identifier_field'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Webform field name (machine name) for identifier'),
            '#default_value' => $this->configuration['identifier_field'],
            '#required' => TRUE,
        ];
        $form['keycloak']['success_status_field'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Set Success-Status-Field'),
            '#default_value' => $this->configuration['success_status_field'],
            '#description' => $this->t('Optional: Machine-Name eines (versteckten) Webform-Feldes, in das dieser Handler sein Ergebnis schreibt.<br><strong>0</strong> = nicht ausgeführt, <strong>1</strong> = Erfolg, <strong>-1</strong> = Fehler/Misserfolg.<br>Damit lassen sich andere Handler per Webform-Conditions von diesem Handler abhängig machen. Leer lassen, um das Feature zu deaktivieren.'),
        ];
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        $values = $form_state->getValues();
        foreach ($this->configuration as $name => $value) {
            if (array_key_exists($name, $values["keycloak"])) {
                $this->configuration[$name] = $values['keycloak'][$name];
            }
        }
    }

    /**
     * Writes this handler's outcome into the configured success-status field,
     * if one is set, so other handlers can be made conditional on it via
     * Webform Conditions. 0 = not executed, 1 = success, -1 = error/failure.
     */
    protected function setSuccessStatus(WebformSubmissionInterface $webform_submission, int $status): void
    {
        $field = trim($this->configuration['success_status_field'] ?? '');
        if ($field === '') {
            return;
        }
        $webform_submission->setElementData($field, $status);
    }
}

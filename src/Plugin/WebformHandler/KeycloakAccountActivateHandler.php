<?php

namespace Drupal\k7zz_webform_keycloak\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Keycloak Account Creator Webform handler.
 *
 * @WebformHandler(
 *   id = "keycloak_account_activate",
 *   label = @Translation("Keycloak Account Activation"),
 *   category = @Translation("External service"),
 *   description = @Translation("Activate email and account of Keycloak users on submit."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class KeycloakAccountActivateHandler extends KeycloakHandlerBase
{

    public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
    {
        $form = parent::buildKeycloakSettingsForm($form, $form_state);
        return $form;
    }

    public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE): void
    {
        $this->setSuccessStatus($webform_submission, 0);

        if ($webform_submission->isDraft()) {
            \Drupal::logger('kzz_webform_keycloak')
                ->notice('Not submitting draft to keycloak for user activation.');
            parent::postSave($webform_submission, $update);
            return;
        }
        \Drupal::logger('kzz_webform_keycloak')
            ->notice('Submitting to keycloak for user activation.');
        $identifier = $webform_submission->getData()[$this->configuration['identifier_field']] ?? null;

        if ($identifier) {
            if ($this->getConnector()->activateUser($identifier)) {
                \Drupal::logger('kzz_webform_keycloak')
                    ->notice('User %user_id activated.', ['%user_id' => $identifier]);
                \Drupal::messenger()->addStatus('✅ Konto erfolgreich aktiviert.');
                $this->setSuccessStatus($webform_submission, 1);
            } else {
                \Drupal::logger('kzz_webform_keycloak')
                    ->error('Failed to activate user %user_id.', ['%user_id' => $identifier]);
                \Drupal::messenger()->addError('❌ Konto konnte nicht angelegt werden.');
                $this->setSuccessStatus($webform_submission, -1);
            }
        } else {
            $this->setSuccessStatus($webform_submission, -1);
        }
    }
}

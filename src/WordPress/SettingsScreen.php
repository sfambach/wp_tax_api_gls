<?php declare(strict_types=1);

namespace TaxApiGls\WordPress;

use TaxApiGls\Core\Api\Environment;

/**
 * The «GLS» tab parts: gateway, client key and secret (Access) and the «How to connect» box. Returns their HTML
 * (CD-8); the modeller wraps them in its form and draws Save, Test connection and Delete (D-981). The secret is
 * never shown.
 */
final class SettingsScreen
{
    public function __construct(private readonly StoredAccess $access)
    {
    }

    public function credentials(): string
    {
        if ($this->access->fromConstants()) {
            return '<p>' . esc_html(sprintf(
                /* translators: %s: sandbox or production */
                __('Set in wp-config.php (TAXGLS_CLIENT_KEY, TAXGLS_CLIENT_SECRET), gateway: %s. This takes precedence over the fields.', 'taxapigls'),
                $this->access->environment()->value
            )) . '</p>' . $this->lastCheck();
        }

        if (! StoredAccess::vaultAvailable()) {
            return '<p>' . esc_html__('This Taxonomy Modeller has no vault yet (0.1.31 or later), so the access can only be defined in wp-config.php:', 'taxapigls') . '</p>'
                . '<pre>' . esc_html("define('TAXGLS_CLIENT_KEY', '…');\ndefine('TAXGLS_CLIENT_SECRET', '…');\ndefine('TAXGLS_ENVIRONMENT', 'production'); // or 'sandbox'") . '</pre>';
        }

        $html = '<p>' . esc_html__('Client key and secret of your app in the GLS developer portal (Apps → My Apps). The secret is stored encrypted.', 'taxapigls') . '</p>';

        if ($this->access->secretBroken()) {
            $html .= '<div class="notice notice-warning inline"><p>' . esc_html__('The stored secret no longer opens — the salts in wp-config.php changed. Enter it again.', 'taxapigls') . '</p></div>';
        }

        $current = $this->access->environment();
        $options = '';

        foreach ([Environment::Production->value => __('Production (real parcels)', 'taxapigls'), Environment::Sandbox->value => __('Sandbox (test data)', 'taxapigls')] as $value => $label) {
            $options .= '<option value="' . esc_attr($value) . '"' . selected($current->value, $value, false) . '>' . esc_html($label) . '</option>';
        }

        return $html . '<table class="form-table" role="presentation"><tbody>'
            . '<tr><th scope="row"><label for="taxapigls-environment">' . esc_html__('Gateway', 'taxapigls') . '</label></th>'
            . '<td><select id="taxapigls-environment" name="taxapigls_environment">' . $options . '</select></td></tr>'
            . '<tr><th scope="row"><label for="taxapigls-client-key">' . esc_html__('Client key', 'taxapigls') . '</label></th>'
            . '<td><input type="text" class="regular-text" autocomplete="off" id="taxapigls-client-key" name="taxapigls_client_key" value="' . esc_attr($this->access->clientKey()) . '"></td></tr>'
            . '<tr><th scope="row"><label for="taxapigls-client-secret">' . esc_html__('Client secret', 'taxapigls') . '</label></th>'
            . '<td><input type="password" class="regular-text" autocomplete="off" id="taxapigls-client-secret" name="taxapigls_client_secret" value="" placeholder="'
            . esc_attr($this->access->hasStoredSecret() ? __('stored — leave empty to keep it', 'taxapigls') : '') . '"></td></tr>'
            . '</tbody></table>' . $this->lastCheck();
    }

    /** The Settings part: today's calls against the budget. */
    public function fields(): string
    {
        $calls = Tracker::callsToday();

        return '<p class="description">' . esc_html(sprintf(
            /* translators: 1: calls today, 2: daily budget */
            __('GLS calls today: %1$d of %2$d. Other modules ask through the filter taxmod_gls_tracking, only when a page asks for fresh data.', 'taxapigls'),
            $calls['calls'],
            Tracker::DAILY_BUDGET
        )) . '</p>';
    }

    private function lastCheck(): string
    {
        $last = get_option(Plugin::LAST_CHECK);

        if (! is_array($last) || ! isset($last['at'])) {
            return '';
        }

        return '<p>' . esc_html(sprintf(
            /* translators: 1: date, 2: sandbox or production, 3: result */
            __('Last test %1$s (%2$s): %3$s', 'taxapigls'),
            (string) $last['at'],
            (string) ($last['environment'] ?? ''),
            (string) ($last['message'] ?? '')
        )) . '</p>';
    }

    /** The steps of the «How to connect» box the modeller draws beside the tab (D-963), escaped. */
    public static function guide(): string
    {
        $steps = [
            __('Let GLS send its parcel notices to the mailbox of Taxmod Mail (or forward them there). Each notice goes to the order with the same parcel number; it never creates an order.', 'taxapigls'),
            __('For the API: sign in at the GLS developer portal, create an app and enable «Track And Trace V1» (it uses the «Authentication API v2»).', 'taxapigls'),
            __('Copy client key and secret of the app (Apps → My Apps). Never post them in a chat.', 'taxapigls'),
            __('Enter both here, save and click «Test connection». If the test says the web server cannot reach GLS, your host blocks outgoing calls.', 'taxapigls'),
        ];

        return '<ol>' . implode('', array_map(static fn (string $step): string => '<li>' . esc_html($step) . '</li>', $steps)) . '</ol>';
    }
}

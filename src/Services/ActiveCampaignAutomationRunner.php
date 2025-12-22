<?php

namespace XaviCabot\FilamentActiveCampaign\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomationLog;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignField;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignList;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;

class ActiveCampaignAutomationRunner
{
    public function __construct(
        protected ActiveCampaignService $acService
    ) {
    }

    /**
     * API pública: lanzar un trigger libre desde tu proyecto con un usuario autenticado.
     *
     * @param  string  $event   Ej: user.registered, wallet.first_deposit
     * @param  Authenticatable  $user
     * @param  array   $context Datos extra: amount, plan, etc.
     */
    public function trigger(string $event, Authenticatable $user, array $context = []): void
    {
        $contactData = [
            'email'     => $user->email,
            'firstName' => $user->name ?? '',
        ];

        $this->runForEventGeneric($event, $user, $contactData, $context);
    }

    /**
     * Nuevo: lanzar un trigger sin usuario registrado, proporcionando email y datos opcionales.
     *
     * @param string $event
     * @param string $email
     * @param array $contactData Opcional: [firstName, lastName, phone, ...]
     * @param array $context     Contexto opcional para plantillas {ctx.*}
     */
    public function triggerWithEmail(string $event, string $email, array $contactData = [], array $context = []): void
    {
        $contactData = array_merge(['email' => $email], $contactData);

        $this->runForEventGeneric($event, null, $contactData, $context);
    }

    /**
     * Construye un “plan de ejecución” sin tocar ActiveCampaign.
     * Útil para preview y para logs.
     */
    public function buildExecutionPlan(
        ActiveCampaignAutomation $automation,
        ?Authenticatable $user,
        array $context = []
    ): array {
        $warnings = [];

        // Lista
        $listAcId = $automation->list_ac_id ?: null;
        $resolvedListAcId = null;

        if ($listAcId) {
            $list = ActiveCampaignList::query()
                ->where('ac_id', $listAcId)
                ->first();

            if (! $list) {
                $warnings[] = [
                    'type'    => 'missing_list',
                    'message' => "List with ac_id={$listAcId} not found locally. Metadata may be out of sync.",
                    'ac_id'   => $listAcId,
                ];

                // seguimos usando el ac_id bruto para no romper nada
                $resolvedListAcId = $listAcId;
            } else {
                $resolvedListAcId = $list->ac_id;
            }
        }

        // Tags (ac_id => name)
        $tags = [];
        $tagIds = collect($automation->tag_ac_ids ?? [])
            ->filter()
            ->values();

        if ($tagIds->isNotEmpty()) {
            $existingTags = ActiveCampaignTag::query()
                ->whereIn('ac_id', $tagIds->all())
                ->pluck('name', 'ac_id')
                ->toArray();

            $tags = $existingTags;

            // detectar tags faltantes
            $missingTagIds = array_diff($tagIds->all(), array_keys($existingTags));
            foreach ($missingTagIds as $missingAcId) {
                $warnings[] = [
                    'type'    => 'missing_tag',
                    'message' => "Tag with ac_id={$missingAcId} not found locally. Metadata may be out of sync.",
                    'ac_id'   => $missingAcId,
                ];
            }
        }

        // Campos personalizados (custom fields)
        $customFields = [];
        foreach ($automation->fields ?? [] as $fieldConfig) {
            $fieldAcId     = Arr::get($fieldConfig, 'field_ac_id');
            $valueTemplate = Arr::get($fieldConfig, 'value_template', '');

            if (! $fieldAcId || $valueTemplate === '') {
                continue;
            }

            $field = ActiveCampaignField::query()
                ->where('ac_id', $fieldAcId)
                ->first();

            if (! $field) {
                $warnings[] = [
                    'type'    => 'missing_field',
                    'message' => "Field with ac_id={$fieldAcId} not found locally. Metadata may be out of sync.",
                    'ac_id'   => $fieldAcId,
                ];
                continue;
            }

            $value = $this->renderTemplate($valueTemplate, $user, $context);

            $customFields[] = [
                'field_ac_id' => $fieldAcId,
                'field_name'  => $field->name,
                'value'       => $value,
            ];
        }

        // System fields (firstName, lastName, phone, etc.)
        $systemFields = [];
        foreach ($automation->system_fields ?? [] as $sysField => $template) {
            $systemFields[$sysField] = $this->renderTemplate($template, $user, $context);
        }

        return [
            'list_ac_id'    => $resolvedListAcId,
            'tags'          => $tags,          // [ac_id => name]
            'custom_fields' => $customFields,  // array de arrays
            'system_fields' => $systemFields,  // [field_name => value]
            'warnings'      => $warnings,      // array de warnings
        ];
    }

    /**
     * Ejecuta todas las automatizaciones de un evento,
     * generando logs de éxito / error.
     */
    protected function runForEvent(string $event, Authenticatable $user, array $context = []): void
    {
        $contactData = [
            'email'     => $user->email,
            'firstName' => $user->name ?? '',
        ];

        $this->runForEventGeneric($event, $user, $contactData, $context);
    }

    /**
     * Ejecución genérica permitiendo usuario opcional y datos de contacto explícitos.
     */
    protected function runForEventGeneric(string $event, ?Authenticatable $user, array $contactData, array $context = []): void
    {
        $automations = ActiveCampaignAutomation::query()
            ->where('event', $event)
            ->where('is_active', true)
            ->get();

        if ($automations->isEmpty()) {
            return;
        }

        // Aseguramos email
        if (empty($contactData['email'])) {
            throw new \InvalidArgumentException('Contact email is required to trigger an automation.');
        }

        $contactId = $this->acService->getOrCreateContactIdByEmail($contactData);
        $contactEmail = (string) $contactData['email'];

        foreach ($automations as $automation) {
            // Construimos el plan para logs / preview
            $plan = $this->buildExecutionPlan($automation, $user, $context);

            try {
                // Ejecutamos la lógica real
                $this->runAutomation($automation, $user, $contactId, $contactEmail, $context);

                // Log OK
                $this->logExecution($automation, $user, $event, $context, $plan, null);
            } catch (\Throwable $e) {
                // Log con error
                $this->logExecution($automation, $user, $event, $context, $plan, $e);
            }
        }
    }

    /**
     * Guarda un log de ejecución de automatización.
     */
    protected function logExecution(
        ActiveCampaignAutomation $automation,
        ?Authenticatable $user,
        string $event,
        array $context,
        array $plan,
        ?\Throwable $error
    ): void {
        ActiveCampaignAutomationLog::create([
            'automation_id' => $automation->id,
            'user_id'       => method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : null,
            'event'         => $event,
            'success'       => $error === null,
            'context'       => $context,
            'payload'       => $plan,
            'error_message' => $error?->getMessage(),
        ]);
    }

    protected function ensureActiveCampaignContactId(Authenticatable $user): string
    {
        if (isset($user->activecampaign_contact_id) && $user->activecampaign_contact_id) {
            return (string) $user->activecampaign_contact_id;
        }

        $contactId = $this->acService->getOrCreateContactIdByEmail([
            'email'     => $user->email,
            'firstName' => $user->name ?? '',
        ]);

        // Si el modelo tiene la columna, lo guardamos (si no, pasamos de largo).
        if ($this->hasColumn($user, 'activecampaign_contact_id')) {
            $user->activecampaign_contact_id = $contactId;
            $user->save();
        }

        return $contactId;
    }

    protected function runAutomation(
        ActiveCampaignAutomation $automation,
        ?Authenticatable $user,
        string $contactId,
        string $contactEmail,
        array $context = []
    ): void {
        // Lista
        if ($automation->list_ac_id) {
            $this->subscribeToList($automation->list_ac_id, $contactId);
        }

        // Tags
        $tagIds = collect($automation->tag_ac_ids ?? [])
            ->filter()
            ->values();

        foreach ($tagIds as $tagAcId) {
            $this->attachTagByAcId($tagAcId, $contactId);
        }

        // Campos (custom fields)
        foreach ($automation->fields ?? [] as $fieldConfig) {
            $fieldAcId     = Arr::get($fieldConfig, 'field_ac_id');
            $valueTemplate = Arr::get($fieldConfig, 'value_template', '');

            if (! $fieldAcId || $valueTemplate === '') {
                continue;
            }

            $this->setFieldByAcId($fieldAcId, $contactId, $user, $valueTemplate, $context);
        }

        // --- SYSTEM FIELDS (firstName, lastName, phone, etc.) ----
        if (! empty($automation->system_fields)) {
            $payload = [];

            foreach ($automation->system_fields as $sysField => $template) {
                $payload[$sysField] = $this->renderTemplate($template, $user, $context);
            }

            // Hacemos un syncContact incremental
            $this->acService->syncContact(array_merge([
                'email' => $contactEmail,
            ], $payload));
        }
    }

    protected function subscribeToList(string $listAcId, string $contactId): void
    {
        $list = ActiveCampaignList::query()
            ->where('ac_id', $listAcId)
            ->first();

        $listId = $list?->ac_id ?? $listAcId;

        $this->acService->addContactToList($contactId, $listId);
    }

    protected function attachTagByAcId(string $tagAcId, string $contactId): void
    {
        $tag = ActiveCampaignTag::query()
            ->where('ac_id', $tagAcId)
            ->first();

        if (! $tag) {
            return;
        }

        $this->acService->addTagToContact($contactId, $tag->name);
    }

    protected function setFieldByAcId(
        string $fieldAcId,
        string $contactId,
        ?Authenticatable $user,
        string $valueTemplate,
        array $context = []
    ): void {
        $field = ActiveCampaignField::query()
            ->where('ac_id', $fieldAcId)
            ->first();

        if (! $field) {
            return;
        }

        $value = $this->renderTemplate($valueTemplate, $user, $context);

        $this->acService->setFieldValueForContact($contactId, $field->name, $value);
    }

    protected function renderTemplate(string $template, ?Authenticatable $user, array $context = []): string
    {
        // Aseguramos string
        $result = (string) $template;

        // 1) Placeholders globales sencillos
        $result = Str::of($result)
            ->replace('{now}', now()->toDateTimeString())
            ->replace('{now_date}', now()->toDateString())
            ->value();

        // 2) Placeholders dinámicos {user.*} y {ctx.*}
        $result = preg_replace_callback('/{(user|ctx)\.([^}]+)}/', function (array $matches) use ($user, $context) {
            $full = $matches[0];   // {user.profile.language.name}
            $root = $matches[1];   // user | ctx
            $path = $matches[2];   // profile.language.name

            if ($root === 'user') {
                $value = data_get($user, $path);
            } else {
                $value = data_get($context, $path);
            }

            // Si no hay valor, dejamos el placeholder tal cual para no vaciar la plantilla
            if ($value === null) {
                return $full;
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            if (is_object($value) && method_exists($value, '__toString')) {
                return (string) $value;
            }

            // Arrays / objetos sin __toString → JSON
            return json_encode($value);
        }, $result);

        return $result;
    }

    protected function hasColumn(Authenticatable $user, string $column): bool
    {
        try {
            $table = $user->getTable();

            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}

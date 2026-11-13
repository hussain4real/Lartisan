<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Download, FileUp, MapPin, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import OfflineDraftBanner from '@/components/OfflineDraftBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOfflineDraft } from '@/composables/useOfflineDraft';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { store as storeFieldVisit } from '@/routes/artisan/field-visits';
import { store } from '@/routes/artisan/kyc';
import type {
    ArtisanDashboardProfile,
    KycSubmissionDetail,
    Team,
} from '@/types';
import type { FieldVisitTerritoryOption } from '@/types/artisans';

type Props = {
    currentTeam: Team;
    profile: Pick<
        ArtisanDashboardProfile,
        'id' | 'businessName' | 'verificationStatus'
    > & {
        territoryId?: number | null;
    };
    collections: string[];
    latestSubmission: KycSubmissionDetail | null;
    canRecordFieldVisit?: boolean;
    fieldVisitStatuses?: string[];
    territories?: FieldVisitTerritoryOption[];
};

const props = withDefaults(defineProps<Props>(), {
    canRecordFieldVisit: false,
    fieldVisitStatuses: () => [
        'scheduled',
        'in_progress',
        'completed',
        'cancelled',
        'flagged',
    ],
    territories: () => [],
});

const kycDraft = useOfflineDraft<{ notes: string }>(
    `kyc-notes:${props.profile.id}`,
    {
        notes: '',
    },
);

const fieldVisitDraft = useOfflineDraft<{
    status: string;
    territory_id: string;
    visited_at: string;
    latitude: string;
    longitude: string;
    notes: string;
    shop_exists: boolean;
    identity_seen: boolean;
    tools_verified: boolean;
}>(`field-visit:${props.profile.id}`, {
    status: 'completed',
    territory_id: props.profile.territoryId
        ? String(props.profile.territoryId)
        : props.territories[0]
          ? String(props.territories[0].id)
          : '',
    visited_at: '',
    latitude: '',
    longitude: '',
    notes: '',
    shop_exists: false,
    identity_seen: false,
    tools_verified: false,
});

const gpsStatusMessage = ref<string | null>(null);
const isCapturingGps = ref(false);

const captureGpsCoordinates = (): void => {
    if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
        gpsStatusMessage.value =
            'Geolocation is unavailable on this browser. Enter coordinates manually if needed.';

        return;
    }

    isCapturingGps.value = true;
    gpsStatusMessage.value = 'Capturing field GPS coordinates...';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            fieldVisitDraft.draft.value.latitude =
                position.coords.latitude.toFixed(6);
            fieldVisitDraft.draft.value.longitude =
                position.coords.longitude.toFixed(6);
            fieldVisitDraft.saveDraft(
                'GPS coordinates captured and saved to local draft.',
            );
            gpsStatusMessage.value = 'GPS coordinates captured.';
            isCapturingGps.value = false;
        },
        () => {
            gpsStatusMessage.value =
                'Unable to read GPS location. Enter coordinates manually if needed.';
            isCapturingGps.value = false;
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
        },
    );
};

const collectionLabel = (collection: string) =>
    collection
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Artisan',
                href: artisanDashboard(props.currentTeam.slug).url,
            },
            {
                title: 'KYC',
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Artisan KYC" />

    <h1 class="sr-only">Artisan KYC</h1>

    <div class="flex flex-col gap-8 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="KYC"
                :description="profile.businessName"
            />
            <Badge variant="secondary" class="w-fit gap-1.5">
                <ShieldCheck class="size-3.5" />
                {{ profile.verificationStatus }}
            </Badge>
        </div>

        <section
            class="grid gap-5 rounded-lg border p-5 lg:grid-cols-[1fr_1.4fr]"
        >
            <Heading variant="small" title="Submit evidence" />

            <OfflineDraftBanner
                :has-draft="kycDraft.hasDraft.value"
                :is-online="kycDraft.isOnline.value"
                :queued-for-sync="kycDraft.queuedForSync.value"
                :saved-at="kycDraft.savedAt.value"
                :status-message="kycDraft.statusMessage.value"
                label="KYC verification notes draft"
                @save="kycDraft.saveDraft()"
                @clear="kycDraft.clearDraft('Local KYC draft cleared.')"
            />

            <Form
                v-bind="store.form(props.currentTeam.slug)"
                class="grid gap-5"
                v-slot="{ errors, processing, progress }"
                @submit.capture="kycDraft.handleOfflineSubmit"
                @success="
                    kycDraft.clearDraft(
                        'KYC submission uploaded and local draft cleared.',
                    )
                "
            >
                <div
                    v-for="collection in collections"
                    :key="collection"
                    class="grid gap-2"
                >
                    <Label :for="collection">{{
                        collectionLabel(collection)
                    }}</Label>
                    <Input
                        :id="collection"
                        :name="collection"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                    />
                    <InputError :message="errors[collection]" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        v-model="kycDraft.draft.value.notes"
                        name="notes"
                        rows="4"
                        maxlength="2000"
                        class="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <progress
                    v-if="progress"
                    class="h-2 w-full"
                    :value="progress.percentage"
                    max="100"
                />

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        <FileUp />
                        Submit
                    </Button>
                </div>
            </Form>
        </section>

        <section
            v-if="canRecordFieldVisit"
            data-test="field-visit-section"
            class="space-y-4 rounded-lg border p-6"
        >
            <Heading
                variant="small"
                title="Field visit verification"
                description="Capture field inspection notes and GPS coordinates even when connectivity drops."
            />

            <OfflineDraftBanner
                data-test="field-visit-draft-banner"
                :has-draft="fieldVisitDraft.hasDraft.value"
                :is-online="fieldVisitDraft.isOnline.value"
                :queued-for-sync="fieldVisitDraft.queuedForSync.value"
                :saved-at="fieldVisitDraft.savedAt.value"
                :status-message="fieldVisitDraft.statusMessage.value"
                label="Field visit draft"
                @save="fieldVisitDraft.saveDraft()"
                @clear="
                    fieldVisitDraft.clearDraft(
                        'Local field visit draft cleared.',
                    )
                "
            />

            <Form
                v-bind="storeFieldVisit.form(props.currentTeam.slug)"
                class="grid gap-5"
                v-slot="{ errors, processing }"
                @submit.capture="fieldVisitDraft.handleOfflineSubmit"
                @success="
                    fieldVisitDraft.clearDraft(
                        'Field visit recorded and local draft cleared.',
                    )
                "
            >
                <input
                    v-if="latestSubmission"
                    type="hidden"
                    name="kyc_submission_id"
                    :value="latestSubmission.id"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="field_visit_status">Visit status</Label>
                        <select
                            id="field_visit_status"
                            v-model="fieldVisitDraft.draft.value.status"
                            name="status"
                            data-test="field-visit-status"
                            class="h-10 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option
                                v-for="visitStatus in fieldVisitStatuses"
                                :key="visitStatus"
                                :value="visitStatus"
                            >
                                {{
                                    visitStatus
                                        .replace(/_/g, ' ')
                                        .replace(/\b\w/g, (character) =>
                                            character.toUpperCase(),
                                        )
                                }}
                            </option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="field_visit_territory_id">
                            Territory
                        </Label>
                        <select
                            id="field_visit_territory_id"
                            v-model="fieldVisitDraft.draft.value.territory_id"
                            name="territory_id"
                            data-test="field-visit-territory"
                            class="h-10 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Default profile territory</option>
                            <option
                                v-for="territory in territories"
                                :key="territory.id"
                                :value="String(territory.id)"
                            >
                                {{ territory.name }}
                            </option>
                        </select>
                        <InputError :message="errors.territory_id" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="field_visit_visited_at">Visited at</Label>
                        <Input
                            id="field_visit_visited_at"
                            v-model="fieldVisitDraft.draft.value.visited_at"
                            type="datetime-local"
                            name="visited_at"
                            data-test="field-visit-visited-at"
                        />
                        <InputError :message="errors.visited_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="field_visit_latitude">Latitude</Label>
                        <Input
                            id="field_visit_latitude"
                            v-model="fieldVisitDraft.draft.value.latitude"
                            type="number"
                            step="any"
                            min="-90"
                            max="90"
                            name="latitude"
                            placeholder="9.076478"
                            data-test="field-visit-latitude"
                        />
                        <InputError :message="errors.latitude" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="field_visit_longitude">Longitude</Label>
                        <Input
                            id="field_visit_longitude"
                            v-model="fieldVisitDraft.draft.value.longitude"
                            type="number"
                            step="any"
                            min="-180"
                            max="180"
                            name="longitude"
                            placeholder="7.468659"
                            data-test="field-visit-longitude"
                        />
                        <InputError :message="errors.longitude" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="min-h-10"
                        :aria-disabled="isCapturingGps"
                        data-test="field-visit-capture-gps"
                        @click="captureGpsCoordinates"
                    >
                        <MapPin />
                        Capture GPS
                    </Button>
                    <span
                        v-if="gpsStatusMessage"
                        role="status"
                        aria-live="polite"
                        class="text-xs text-muted-foreground"
                        data-test="field-visit-gps-status"
                    >
                        {{ gpsStatusMessage }}
                    </span>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label
                        class="flex min-h-10 items-center gap-2 rounded-md border px-3 py-2 text-sm"
                    >
                        <input
                            type="hidden"
                            name="checklist[shop_exists]"
                            value="0"
                        />
                        <input
                            v-model="fieldVisitDraft.draft.value.shop_exists"
                            type="checkbox"
                            name="checklist[shop_exists]"
                            value="1"
                            data-test="field-visit-shop-exists"
                        />
                        <span>Shop exists</span>
                    </label>
                    <label
                        class="flex min-h-10 items-center gap-2 rounded-md border px-3 py-2 text-sm"
                    >
                        <input
                            type="hidden"
                            name="checklist[identity_seen]"
                            value="0"
                        />
                        <input
                            v-model="fieldVisitDraft.draft.value.identity_seen"
                            type="checkbox"
                            name="checklist[identity_seen]"
                            value="1"
                            data-test="field-visit-identity-seen"
                        />
                        <span>ID seen</span>
                    </label>
                    <label
                        class="flex min-h-10 items-center gap-2 rounded-md border px-3 py-2 text-sm"
                    >
                        <input
                            type="hidden"
                            name="checklist[tools_verified]"
                            value="0"
                        />
                        <input
                            v-model="fieldVisitDraft.draft.value.tools_verified"
                            type="checkbox"
                            name="checklist[tools_verified]"
                            value="1"
                            data-test="field-visit-tools-verified"
                        />
                        <span>Tools verified</span>
                    </label>
                </div>

                <div class="grid gap-2">
                    <Label for="field_visit_notes">Field visit notes</Label>
                    <textarea
                        id="field_visit_notes"
                        v-model="fieldVisitDraft.draft.value.notes"
                        name="notes"
                        rows="3"
                        maxlength="2000"
                        data-test="field-visit-notes"
                        class="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        data-test="field-visit-submit"
                        :disabled="processing"
                    >
                        Record field visit
                    </Button>
                </div>
            </Form>
        </section>

        <section class="space-y-4">
            <Heading variant="small" title="Latest submission" />

            <div v-if="latestSubmission" class="rounded-lg border p-5">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="font-medium">
                            Submission #{{ latestSubmission.id }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                latestSubmission.submittedAt || 'Not submitted'
                            }}
                        </p>
                    </div>
                    <Badge variant="outline">{{
                        latestSubmission.status
                    }}</Badge>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="collection in collections"
                        :key="collection"
                        class="rounded-md border p-3 text-sm"
                    >
                        <p class="font-medium">
                            {{ collectionLabel(collection) }}
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            {{
                                latestSubmission.media[collection]?.fileName ??
                                'No file'
                            }}
                        </p>
                        <a
                            v-if="latestSubmission.media[collection]?.url"
                            :href="latestSubmission.media[collection]?.url"
                            target="_blank"
                            rel="noreferrer"
                            class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <Download class="size-3.5" />
                            Download
                        </a>
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No KYC submission yet.
            </p>
        </section>
    </div>
</template>

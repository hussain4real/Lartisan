<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { KeyRound } from 'lucide-vue-next';
import { store } from '@/actions/App/Http/Controllers/Identity/AccountClaimController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Claim your account',
        description:
            'Set your password to continue with your Lartisan profile.',
    },
});

defineProps<{
    token: string;
}>();
</script>

<template>
    <Head title="Claim account" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <input v-if="token" type="hidden" name="token" :value="token" />

        <div class="grid gap-6">
            <div v-if="!token" class="grid gap-2">
                <Label for="token">Claim token</Label>
                <Input id="token" name="token" required autocomplete="off" />
                <InputError :message="errors.token" />
            </div>
            <InputError v-else :message="errors.token" />

            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    name="name"
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button type="submit" class="mt-2 w-full" :disabled="processing">
                <Spinner v-if="processing" />
                <KeyRound v-else />
                Claim account
            </Button>
        </div>
    </Form>
</template>

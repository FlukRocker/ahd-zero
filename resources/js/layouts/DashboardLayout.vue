<script setup lang="ts">
import DashboardNavbar from '@/Components/DashboardNavbar.vue';
</script>

<template>
    <div class="min-h-screen bg-[#09090b] text-white font-sans flex flex-col">
        <DashboardNavbar />
        
        <!-- Main Content Area -->
        <div class="p-6 flex-1 flex flex-col">
            <slot />
        </div>
    </div>
</template>

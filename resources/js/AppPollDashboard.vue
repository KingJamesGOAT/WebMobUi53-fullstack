<script setup>
  import { onMounted } from 'vue';
  import PollCreateForm from './components/PollCreateForm.vue';
  import PollTable from './components/PollTable.vue';
  import { useFetchApi } from '@/composables/useFetchApi';
  import { usePollStore } from '@/stores/usePollStore';

  const { fetchApi } = useFetchApi();
  const { polls, setPolls } = usePollStore();

  onMounted(async () => {
    try {
      const data = await fetchApi({ url: '/polls' });
      setPolls(data);
    } catch (error) {
      console.error('Failed to fetch polls:', error);
    }
  });
</script>

<template>
  <div class="space-y-8 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Tableau de bord</h1>
    </div>

    <PollCreateForm />
    
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-white">Mes sondages récents</h2>
      </div>
      <PollTable :polls="polls" />
    </div>
  </div>
</template>

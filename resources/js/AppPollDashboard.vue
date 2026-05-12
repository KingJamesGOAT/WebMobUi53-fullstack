<script setup>
  import { ref, onMounted } from 'vue';
  import PollCreateForm from './components/PollCreateForm.vue';
  import PollTable from './components/PollTable.vue';
  import { useFetchApi } from '@/composables/useFetchApi';
  import { usePollStore } from '@/stores/usePollStore';

  const { fetchApi } = useFetchApi();
  const { polls, setPolls } = usePollStore();

  const pollToEdit = ref(null);

  function handleEditPoll(poll) {
    pollToEdit.value = poll;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function startDraft(poll) {
    try {
      // 1. Récupérer les options complètes via l'URL publique
      const detailedPoll = await fetchApi({ url: `/polls/${poll.secret_token}` });
      const options = (detailedPoll && detailedPoll.options) 
        ? detailedPoll.options.map(o => o.label) 
        : [];

      // 2. Préparer les données en forçant isDraft à false
      const data = {
        title: poll.title,
        question: poll.question,
        options: options,
        isMultipleChoice: !!poll.allow_multiple_choices,
        isPublicResults: !!poll.results_public,
        isDraft: false
      };

      // 3. Envoyer la requête PUT pour mettre à jour
      const updatedPoll = await fetchApi({
        url: `/polls/${poll.id}`,
        method: 'PUT',
        data: data
      });

      // 4. Mettre à jour la liste pour refléter le changement
      setPolls(polls.value.map(p => p.id === updatedPoll.id ? updatedPoll : p));
    } catch (error) {
      console.error('Failed to start draft:', error);
    }
  }

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

    <PollCreateForm :pollToEdit="pollToEdit" @cancel-edit="pollToEdit = null" />
    
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-white">Mes sondages récents</h2>
      </div>
      <PollTable :polls="polls" @edit-poll="handleEditPoll" @start-poll="startDraft" />
    </div>
  </div>
</template>

<script setup>
  import { useFetchApi } from '@/composables/useFetchApi';
  import { usePollStore } from '@/stores/usePollStore';

  const props = defineProps({
    polls: { type: Array, default: () => [] },
  });

  const { fetchApi } = useFetchApi();
  const { setPolls } = usePollStore();

  async function deletePoll(id) {
    try {
      await fetchApi({
        url: `/polls/${id}`,
        method: 'DELETE'
      });
      // Une fois la requête réussie, retire le sondage de la liste
      setPolls(props.polls.filter(poll => poll.id !== id));
    } catch (error) {
      console.error('Erreur lors de la suppression du sondage:', error);
    }
  }
</script>

<template>
  <div v-if="polls.length === 0" class="p-8 text-center text-slate-500 dark:text-slate-400">
    <p>Vous n'avez pas encore créé de sondage.</p>
  </div>

  <div v-else class="overflow-x-auto">
    <table class="w-full text-left whitespace-nowrap">
      <thead class="bg-slate-50 dark:bg-slate-700/50">
        <tr>
          <th class="px-6 py-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Titre</th>
          <th class="px-6 py-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Question</th>
          <th class="px-6 py-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Date de création</th>
          <th class="px-6 py-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Partage</th>
          <th class="px-6 py-3 text-sm font-semibold text-slate-600 dark:text-slate-300 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
        <tr v-for="poll in polls" :key="poll.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
          <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">{{ poll.title || 'Sans titre' }}</td>
          <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ poll.question }}</td>
          <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
            {{ new Date(poll.created_at).toLocaleDateString('fr-FR') }}
          </td>
          <td class="px-6 py-4 text-sm">
            <a 
              :href="'/vote/' + poll.secret_token" 
              target="_blank" 
              class="text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300 font-medium hover:underline"
            >
              Lien de vote
            </a>
          </td>
          <td class="px-6 py-4 text-sm text-right">
            <button 
              @click="deletePoll(poll.id)" 
              class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-md text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800"
            >
              Supprimer
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
<script setup>
  import { ref, computed, onMounted } from 'vue';
  import { useFetchApi } from '@/composables/useFetchApi';
  import { usePolling } from '@/composables/usePolling';

  const { fetchApi } = useFetchApi();

  const token = ref('');
  const poll = ref(null);
  const selectedOptions = ref([]); // Tableau pour gérer choix simple et multiple
  const successMessage = ref('');
  const errorMessage = ref('');
  const hasVoted = ref(false);

  async function fetchPoll() {
    if (!token.value) return;
    try {
      const data = await fetchApi({ url: '/polls/' + token.value });
      poll.value = data;
    } catch (error) {
      errorMessage.value = 'Impossible de charger le sondage.';
    }
  }

  onMounted(() => {
    token.value = document.getElementById('app-vote').dataset.token;
    // Vérifier si le navigateur a déjà voté
    if (localStorage.getItem('voted_' + token.value)) {
      hasVoted.value = true;
    }
    fetchPoll();
  });

  usePolling(fetchPoll, 3000);

  // L'API nous envoie is_expired=true si ends_at est dépassé
  const isExpired = computed(() => {
    if (!poll.value) return false;
    return poll.value.is_expired === true;
  });

  const totalVotes = computed(() => {
    if (!poll.value || !poll.value.options) return 0;
    return poll.value.options.reduce((sum, opt) => sum + opt.votes_count, 0);
  });

  function getPercentage(votesCount) {
    if (totalVotes.value === 0) return 0;
    return Math.round((votesCount / totalVotes.value) * 100);
  }

  async function submitVote() {
    successMessage.value = '';
    errorMessage.value = '';

    try {
      // Boucle pour envoyer un vote par option cochée (pour le choix multiple)
      for (const optionId of selectedOptions.value) {
        await fetchApi({
          url: '/polls/' + token.value + '/vote',
          data: { option_id: optionId },
        });
      }

      successMessage.value = 'Votre vote a été enregistré !';
      
      // Bloquer le formulaire dans le navigateur
      hasVoted.value = true;
      localStorage.setItem('voted_' + token.value, 'true');

      await fetchPoll();
    } catch (error) {
      errorMessage.value = error.data?.message || 'Une erreur est survenue.';
    }
  }
</script>

<template>
  <div class="bg-white dark:bg-slate-800 shadow rounded-lg p-6 max-w-2xl mx-auto mt-8">
    <div v-if="!poll && !errorMessage" class="text-center py-8 text-slate-500 dark:text-slate-400">
      <p>Chargement du sondage...</p>
    </div>

    <div v-if="!poll && errorMessage" class="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-4 rounded-md text-center">
      <p>{{ errorMessage }}</p>
    </div>

    <div v-if="poll" class="space-y-6">
      <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ poll.question }}</h1>

      <!-- Message d'avertissement si le sondage est expiré -->
      <div v-if="isExpired" class="p-4 bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 rounded-md text-center font-medium">
        ⏰ Ce sondage est terminé. Il n'est plus possible de voter.
      </div>

      <!-- Formulaire de vote : masqué si expiré ou si l'utilisateur a déjà voté -->
      <form v-else-if="!hasVoted" @submit.prevent="submitVote" class="space-y-4">
        <div class="space-y-3">
          <div v-for="option in poll.options" :key="option.id" class="flex items-center">
            <input
              :type="poll.allow_multiple_choices ? 'checkbox' : 'radio'"
              :id="'option-' + option.id"
              :value="option.id"
              v-model="selectedOptions"
              name="poll-option"
              class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-slate-300 dark:border-slate-600 dark:bg-slate-900"
            />
            <label :for="'option-' + option.id" class="ml-3 block text-base font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
              {{ option.label }}
            </label>
          </div>
        </div>

        <div class="pt-2">
          <button 
            type="submit" 
            :disabled="selectedOptions.length === 0" 
            class="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white font-medium py-2 px-6 rounded-md transition focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Voter
          </button>
        </div>
      </form>

      <div v-else class="p-4 bg-teal-50 dark:bg-teal-900/30 text-teal-800 dark:text-teal-300 rounded-md text-center font-medium">
        Merci d'avoir participé à ce sondage !
      </div>

      <div v-if="poll.results_public" class="pt-6 border-t border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-white mb-4">
          Résultats <span class="text-sm font-normal text-slate-500 dark:text-slate-400">({{ totalVotes }} votes)</span>
        </h2>

        <div class="space-y-4">
          <div v-for="option in poll.options" :key="'result-' + option.id">
            <div class="flex justify-between text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
              <span>{{ option.label }}</span>
              <span>{{ option.votes_count }} ({{ getPercentage(option.votes_count) }}%)</span>
            </div>
            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
              <div class="bg-teal-600 h-2.5 rounded-full transition-all duration-500 ease-out" :style="{ width: getPercentage(option.votes_count) + '%' }"></div>
            </div>
          </div>
        </div>
      </div>
      
      <div v-else class="pt-6 border-t border-slate-200 dark:border-slate-700 text-center text-slate-500 dark:text-slate-400 text-sm">
        Les statistiques de ce sondage sont masquées par le créateur.
      </div>

    </div>
  </div>
</template>

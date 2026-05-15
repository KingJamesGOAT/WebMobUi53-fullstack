<script setup>
  import { ref, watch } from 'vue';
  import { usePollStore } from '@/stores/usePollStore';

  const props = defineProps({
    pollToEdit: { type: Object, default: null }
  });

  const emit = defineEmits(['cancel-edit']);

  const { addPoll, setPolls, polls } = usePollStore();

  const title = ref('');
  const question = ref('');
  
  // Nouvelles variables pour les options et la configuration
  const options = ref(['', '']);
  const isMultipleChoice = ref(false);
  const isPublicResults = ref(false);
  const isDraft = ref(false);
  // Durée en heures (null = pas de limite)
  const duration = ref(null);

  function addOption() {
    options.value.push('');
  }

  function resetForm() {
    title.value = '';
    question.value = '';
    options.value = ['', ''];
    isMultipleChoice.value = false;
    isPublicResults.value = false;
    isDraft.value = false;
    duration.value = null;
  }

  function cancelEdit() {
    resetForm();
    emit('cancel-edit');
  }

  watch(() => props.pollToEdit, (newVal) => {
    if (newVal) {
      title.value = newVal.title || '';
      question.value = newVal.question || '';
      isMultipleChoice.value = !!newVal.allow_multiple_choices;
      isPublicResults.value = !!newVal.results_public;
      isDraft.value = !!newVal.is_draft;
      duration.value = newVal.duration || null;

      // Les options sont déjà disponibles dans la prop transmise par le dashboard
      // (chargées via index() avec withCount). Pas besoin d'un appel réseau supplémentaire.
      if (newVal.options && newVal.options.length > 0) {
        options.value = newVal.options.map(o => o.label);
      } else {
        options.value = ['', ''];
      }
    } else {
      resetForm();
    }
  });

  async function submitForm() {
    try {
      const data = {
        title: title.value,
        question: question.value,
        options: options.value.filter(opt => opt.trim() !== ''),
        isMultipleChoice: isMultipleChoice.value,
        isPublicResults: isPublicResults.value,
        isDraft: isDraft.value,
        // Envoyer null si vide pour effacer une durée existante
        duration: duration.value ? parseInt(duration.value) : null,
      };

      if (props.pollToEdit) {
        // Mode édition : requête PUT
        const updatedPoll = await fetchApi({
          url: `/polls/${props.pollToEdit.id}`,
          method: 'PUT',
          data: data
        });
        
        // Mettre à jour la liste dans le store
        setPolls(polls.value.map(p => p.id === updatedPoll.id ? updatedPoll : p));
        cancelEdit();
      } else {
        // Mode création : requête POST
        const newPoll = await fetchApi({
          url: '/polls',
          data: data,
        });

        addPoll(newPoll);
        resetForm();
      }
    } catch (error) {
      console.error('Failed to save poll:', error);
    }
  }
</script>

<template>
  <div class="bg-white dark:bg-slate-800 shadow rounded-lg p-6">
    <h2 class="text-lg font-semibold text-slate-800 dark:text-white mb-5">
      {{ pollToEdit ? 'Modifier le sondage' : 'Créer un nouveau sondage' }}
    </h2>
    
    <form @submit.prevent="submitForm" class="space-y-5">
      <div>
        <label for="poll_title" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Titre (optionnel)</label>
        <input 
          id="poll_title" 
          v-model="title" 
          type="text" 
          placeholder="Exemple : Soirée de fin d'année" 
          class="w-full border border-slate-300 dark:border-slate-600 rounded-md px-4 py-2 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition"
        />
      </div>

      <div>
        <label for="poll_question" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Question principale</label>
        <input 
          id="poll_question" 
          v-model="question" 
          type="text" 
          placeholder="Quelle est votre disponibilité ?" 
          required 
          class="w-full border border-slate-300 dark:border-slate-600 rounded-md px-4 py-2 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition"
        />
      </div>

      <!-- Section des options de réponse -->
      <div class="space-y-3 border-t border-slate-200 dark:border-slate-700 pt-4">
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Options de réponse</label>
        
        <div v-for="(option, index) in options" :key="index">
          <input 
            v-model="options[index]" 
            type="text" 
            :placeholder="'Option ' + (index + 1)" 
            class="w-full border border-slate-300 dark:border-slate-600 rounded-md px-4 py-2 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition"
          />
        </div>
        
        <button 
          type="button" 
          @click="addOption"
          class="text-sm text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300 font-medium focus:outline-none"
        >
          + Ajouter une option
        </button>
      </div>

      <!-- Section de configuration -->
      <div class="space-y-3 border-t border-slate-200 dark:border-slate-700 pt-4">
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Configuration</label>
        
        <div class="flex items-center">
          <input id="multiple" type="checkbox" v-model="isMultipleChoice" class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-slate-300 rounded dark:border-slate-600 dark:bg-slate-900" />
          <label for="multiple" class="ml-2 block text-sm text-slate-700 dark:text-slate-300">
            Choix multiple
          </label>
        </div>

        <div class="flex items-center">
          <input id="public" type="checkbox" v-model="isPublicResults" class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-slate-300 rounded dark:border-slate-600 dark:bg-slate-900" />
          <label for="public" class="ml-2 block text-sm text-slate-700 dark:text-slate-300">
            Résultats publics
          </label>
        </div>

        <div class="flex items-center">
          <input id="draft" type="checkbox" v-model="isDraft" class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-slate-300 rounded dark:border-slate-600 dark:bg-slate-900" />
          <label for="draft" class="ml-2 block text-sm text-slate-700 dark:text-slate-300">
            Enregistrer comme brouillon
          </label>
        </div>

        <!-- Durée de disponibilité (optionnelle) -->
        <div>
          <label for="poll_duration" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            Durée de disponibilité (en heures, optionnel)
          </label>
          <input
            id="poll_duration"
            v-model="duration"
            type="number"
            min="1"
            placeholder="Ex: 24 pour 24h"
            class="w-full border border-slate-300 dark:border-slate-600 rounded-md px-4 py-2 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition"
          />
        </div>
      </div>

      <div class="pt-2 flex gap-3">
        <button 
          type="submit" 
          class="w-full sm:w-auto bg-teal-600 hover:bg-teal-700 text-white font-medium py-2 px-6 rounded-md transition focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
        >
          {{ pollToEdit ? 'Mettre à jour' : 'Publier le sondage' }}
        </button>
        <button 
          v-if="pollToEdit"
          type="button" 
          @click="cancelEdit"
          class="w-full sm:w-auto bg-slate-200 hover:bg-slate-300 text-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-white font-medium py-2 px-6 rounded-md transition focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
        >
          Annuler
        </button>
      </div>
    </form>
  </div>
</template>

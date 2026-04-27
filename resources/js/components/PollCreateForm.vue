<script setup>
  import { ref } from 'vue';
  import { useFetchApi } from '@/composables/useFetchApi';
  import { usePollStore } from '@/stores/usePollStore';

  // Get the fetchApi function from the composable
  const { fetchApi } = useFetchApi();

  // Get the addPoll function from the store
  const { addPoll } = usePollStore();

  // Reactive variables bound to the form inputs
  const title = ref('');
  const question = ref('');

  // Called when the form is submitted
  async function submitForm() {
    try {
      // POST the form data to the API
      const newPoll = await fetchApi({
        url: '/polls',
        data: {
          title: title.value,
          question: question.value,
        },
      });

      // Add the newly created poll to the store so it appears in the table
      addPoll(newPoll);

      // Clear the form fields after a successful creation
      title.value = '';
      question.value = '';
    } catch (error) {
      console.error('Failed to create poll:', error);
    }
  }
</script>

<template>
  <!-- Simple form to create a new poll -->
  <form @submit.prevent="submitForm">
    <div>
      <label for="poll-title">Titre</label>
      <input id="poll-title" v-model="title" type="text" placeholder="Titre du sondage" />
    </div>

    <div>
      <label for="poll-question">Question</label>
      <input id="poll-question" v-model="question" type="text" placeholder="Votre question" required />
    </div>

    <button type="submit">Créer le sondage</button>
  </form>
</template>

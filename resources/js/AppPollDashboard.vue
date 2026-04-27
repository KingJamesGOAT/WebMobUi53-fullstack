<script setup>
  import { onMounted } from 'vue';
  import PollCreateForm from './components/PollCreateForm.vue';
  import PollTable from './components/PollTable.vue';
  import { useFetchApi } from '@/composables/useFetchApi';
  import { usePollStore } from '@/stores/usePollStore';

  // Get the fetchApi function from the composable
  const { fetchApi } = useFetchApi();

  // Get the reactive polls array and the setter from the store
  const { polls, setPolls } = usePollStore();

  // When the component mounts, fetch the user's polls from the API
  onMounted(async () => {
    try {
      const data = await fetchApi({ url: '/polls' });
      // Store the fetched polls in the shared store
      setPolls(data);
    } catch (error) {
      console.error('Failed to fetch polls:', error);
    }
  });
</script>

<template>
  <!-- Form to create a new poll -->
  <PollCreateForm />

  <!-- Table displaying the user's polls -->
  <PollTable :polls="polls" />
</template>

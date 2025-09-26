import {ref} from "vue";

export default function useModal() {
    const selectedItem = ref(null);
    const showModal = ref(false);

    function openModal(item) {
        selectedItem.value = item;
        showModal.value = true;
    }

    function closeModal() {
        selectedItem.value = null;
        showModal.value = false;
    }

    return {
        openModal,
        closeModal,
        selectedItem,
        showModal
    }
}

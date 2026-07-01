import { watch, ref } from 'vue'

const titleType = ref(localStorage.getItem('title-type') || '')
watch(titleType, (v) => localStorage.setItem('title-type', v))
export default () => ({ titleType })

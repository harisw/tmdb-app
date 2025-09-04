import {getCurrentInstance} from "vue";

export default function useBaseUrl() {
    const {proxy} = getCurrentInstance();
    return proxy.$base_url;
}

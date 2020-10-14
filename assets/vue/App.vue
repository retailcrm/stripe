<template>
    <router-view />
</template>

<script>
    import axios from "axios";
  
    export default {
        name: "App",
        created() {
            axios.interceptors.response.use(undefined, (err) => {
                return new Promise(() => {
                    if (err.response.status === 500) {
                        document.open();
                        document.write(err.response.data);
                        document.close();
                    }
                    throw err;
                });
            });
        },
    }
</script>
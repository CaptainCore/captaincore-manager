<script src="https://unpkg.com/qs@6.5.2/dist/qs.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@2.7.14/dist/vue.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@2.6.15/dist/vuetify.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/vuetify@2.6.15/dist/vuetify.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@mdi/font@5.x/css/materialdesignicons.min.css" rel="stylesheet">

<style>
[v-cloak] > * {
    display:none;
}
[v-cloak]::before {
    display: block;
    position: relative;
    left: 0%;
    top: 0%;
    max-width: 1000px;
    margin:auto;
    padding-bottom: 10em;
}
body #app {
    line-height: initial;
}
.theme--light.v-data-table > .v-data-table__wrapper > table > tbody > tr:hover:not(.v-data-table__expanded__content):not(.v-data-table__empty-wrapper) {
    background: none;
}
input[type=checkbox], input[type=color], input[type=date], input[type=datetime-local], input[type=datetime], input[type=email], input[type=month], input[type=number], input[type=password], input[type=radio], input[type=search], input[type=tel], input[type=text], input[type=time], input[type=url], input[type=week], select, textarea {
    border:0px;
    box-shadow: none;
}
input[type=text]:focus {
    box-shadow: none;
}
#app .theme--light.v-application {
    background: transparent;
}
input.readonly,input[readonly],textarea.readonly,textarea[readonly] {
    background: transparent;
}
</style>
<form action='options.php' method='post'>
<div id="app" v-cloak>
    <v-app >
      <v-main>
      <v-layout>
        <v-row>
        <v-col x12 class="mr-4 mt-4">
            <v-card>
            <v-overlay absolute :value="loading" class="align-start">
                <div style="height: 100px;"></div>
                <v-progress-circular size="128" color="white" indeterminate class="mt-16"></v-progress-circular>
            </v-overlay>
            <v-toolbar flat>
                <v-toolbar-title>CaptainCore</v-toolbar-title>
                <v-spacer></v-spacer>
                <v-toolbar-items>
                   
                </v-toolbar-items>
            </v-toolbar>
            <v-card-text>
                <v-subheader>Configurations</v-subheader>
                <v-container>
                <v-row style="max-width:450px">
                    <v-col>
                        <v-text-field v-model="configurations.path" label="Mount Path" persistent-hint hint="Location where CaptainCore will display on WordPress frontend."></v-text-field>
                    </v-col>
                </v-row>
                <v-row style="max-width:450px">
                    <v-col>
                        <v-select label="Mode" v-model="configurations.mode" :items="modes"></v-select>
                    </v-col>
                </v-row>
                </v-container>
                <v-btn color="primary" @click="saveConfigurations()">Save Configurations</v-btn>
            </v-card-text>
            </v-card>
            <v-snackbar v-model="snackbar">
                {{ response }}
            <template v-slot:action="{ attrs }">
                <v-btn text v-bind="attrs" @click="snackbar = false">Close</v-btn>
            </template>
            </v-snackbar>
        </v-col>
        </v-row>
      </v-layout>
      </v-main>
    </v-app>
  </div>
</form>
<script>
new Vue({
    el: '#app',
    vuetify: new Vuetify({
		theme: {
			themes: {
				light: {
                    primary: '#0073aa',
                    secondary: '#424242',
                    accent: '#82B1FF',
                    error: '#FF5252',
                    info: '#2196F3',
                    success: '#4CAF50',
                    warning: '#FFC107'
                }
			},
		},
	}),
    data: {
        snackbar: false,
        loading: false,
        response: "",
        wp_nonce: "",
        configurations: <?php echo CaptainCore\Configurations::get_json(); ?>,
        modes: [ { text: "Hosting", value: "hosting" }, { text: "Maintenance", value: "maintenance" }],
    },
    mounted() {
        axios.get( '/' ).then(response => {
            html = response.data
            const regex = /var wpApiSettings.+"nonce":"(.+)"/
            const found = html.match(regex);
            if ( typeof found[1] !== 'undefined' ) {
                this.wp_nonce = found[1]
            }
        })
    },
    methods: {
        saveConfigurations() {
            this.loading = true
            axios.post( '/wp-json/captaincore/v1/configurations', {
				configurations: this.configurations
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.configurations = response.data
                this.response = "Configurations updated."
                this.snackbar = true
                this.loading = false
			})
            .catch( error => {
                console.log( error )
            })
        }
    }
})
</script>
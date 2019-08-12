<script src="https://unpkg.com/qs@6.5.2/dist/qs.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@2.5.22/dist/vue.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@1.4.3/dist/vuetify.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/vuetify@1.4.3/dist/vuetify.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/frappe-charts@1.2.0/dist/frappe-charts.min.css" rel="stylesheet">
<script src="/wp-content/plugins/captaincore/public/js/frappe-charts.js"></script>
<style>
body #app {
  line-height: initial;
}
input[type=checkbox], input[type=color], input[type=date], input[type=datetime-local], input[type=datetime], input[type=email], input[type=month], input[type=number], input[type=password], input[type=radio], input[type=search], input[type=tel], input[type=text], input[type=time], input[type=url], input[type=week], select, textarea {
    border:0px;
    box-shadow: none;
}

input[type=text]:focus {
  box-shadow: none;
}
.graph-svg-tip.comparison .title {
	font-size: 12px !important;
}
</style>
<form action='options.php' method='post'>

    <h2>CaptainCore Dashboard</h2>

<div id="app">
    <v-layout class="mr-3 pt-2" v-show="!instance.address">
    <v-flex sm8 pr-1>
      <v-card>
         <v-toolbar light card dense>
          <v-toolbar-title>Connect to a CaptainCore instance</v-toolbar-title>
          <v-spacer></v-spacer>
        </v-toolbar>
        <v-card-text>
           <p>CaptainCore requires a connection in order to run. You can connect to a <a href="https://captaincore.io/sign-up" target="_new">managed instance</a> (Easy) or a <a href="https://captaincore.io/self-hosting" target="_new">self-hosted instance</a> (Developers).</p>
           <v-text-field label="Instance address" required placeholder=" "></v-text-field>
           <v-text-field label="Instance token" required placeholder=" "></v-text-field>
           <v-btn>Connect</v-btn>
        </v-card-text>
    </v-card>
    </v-flex>
    </v-layout>
    <v-layout class="mr-3 pt-2">
    <v-flex sm4 pr-1>
    <v-card v-show="instance.address">
        <v-toolbar light card dense>
          <v-toolbar-title>Connection Status</v-toolbar-title>
          <v-spacer></v-spacer>
          <v-progress-circular :value="100" color="green" size="14" width="10"></v-progress-circular>
        </v-toolbar>
        <v-card-text>
           <p>Connected to CaptainCore instance <strong>{{ instance.address }}</strong> which contains the following information. </p>
            <v-list dense>
            <v-list-tile>
              <v-list-tile-content>
                <v-list-tile-title>Sites: <strong>{{ settings.manifest.sites.count }}</strong></v-list-tile-title>
              </v-list-tile-content>
            </v-list-tile>
            <v-divider></v-divider>
            <v-list-tile>
              <v-list-tile-content>
                <v-list-tile-title>Quicksaves: <strong>{{ settings.manifest.quicksaves.count }}</strong></v-list-tile-title>
              </v-list-tile-content>
            </v-list-tile>
            <v-divider></v-divider>
            <v-list-tile>
              <v-list-tile-content>
                <v-list-tile-title>Storage: <strong>{{ settings.manifest.storage | formatGBs }}</strong></v-list-tile-title>
              </v-list-tile-content>
            </v-list-tile>
            </v-list>
            <div id="chart"></div>
        </v-card-text>
    </v-card>
    </v-flex>
    <v-flex xs8 pl-1>
    <v-card v-show="instance.address">
         <v-toolbar light card dense>
          <v-toolbar-title>Settings</v-toolbar-title>
          <v-spacer></v-spacer>
        </v-toolbar>
        <v-card-text>
           <v-text-field v-model="settings.captaincore_admin_email" label="Site monitor email address" required placeholder=" "></v-text-field>
           <v-text-field v-model="settings.captaincore_tracker" label="Fathom Analytics domain" required placeholder="stats.your-domain.com" persistent-hint hint="Custom domain to enable CaptainCore managed Fathom Analytics instance."></v-text-field>
           <br /><v-btn>Save Settings</v-btn>
        </v-card-text>
    </v-card>
    </v-flex>
    </v-layout>
    <p><a href="/wp-admin/edit.php?post_type=captcore_website">Legacy Custom Post Types</a></p>
</div>
</form>
<script>
var app = new Vue({
  el: '#app',
  data: {
    settings: <?php echo json_encode( get_option( 'captaincore_settings' ) ); ?>,
    instance: { 
        maintenance_logs: {
            count: 12
        },
        address: "<?php if ( defined( "CAPTAINCORE_CLI_ADDRESS" ) ) { echo CAPTAINCORE_CLI_ADDRESS; } ?>"
    }
  },
  methods: {
    generateChart() {
      //quicksaves_storage = this.settings.manifest.quicksaves.storage | formatGBs;
      sites_storage = this.$options.filters.formatGBs( this.settings.manifest.sites.storage );
      sites_percentage = this.settings.manifest.sites.storage / this.settings.manifest.storage;
      sites_percentage = ( sites_percentage * 100 ).toFixed(1);
      quicksaves_storage = this.$options.filters.formatGBs( this.settings.manifest.quicksaves.storage );
      quicksaves_percentage = this.settings.manifest.quicksaves.storage / this.settings.manifest.storage;
      quicksaves_percentage = ( quicksaves_percentage * 100 ).toFixed(1);

      // Generate chart
			chart =	new frappe.Chart( "#chart", {
          data: {
            labels: ["Quicksaves (" + quicksaves_storage + ")","Sites (" + sites_storage + ")"],
            datasets: [
              {
                name: "Data",
                values: [ Math.max(quicksaves_percentage), Math.max(sites_percentage) ],
              }
            ],
          },
          type: "percentage",
          height: 80,
          colors: ["light-blue", "#1564c0"],
          axisOptions: {
            xAxisMode: "span",
            xIsSeries: 0
          },
          barOptions: {
            spaceRatio: 0.1,
            stacked: 0
          }
          });
    }
  },
  filters: {
    formatGBs: function (fileSizeInBytes) {
			fileSizeInBytes = fileSizeInBytes / 1024 / 1024 / 1024;
			return Math.max(fileSizeInBytes, 0.1).toFixed(2) + " GBs";
		},
  },
  mounted() {

    var data = {
				'action': 'captaincore_ajax',
				'command': "fetchConfigs",
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
          this.settings = response.data;
          this.generateChart();
        })
				.catch( error => {
					console.log( error );
				});
  }
});
</script>
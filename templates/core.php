<?php
if ( ! function_exists('is_plugin_active') ) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}
?><!DOCTYPE html>
<html>
<head>
  <title><?php echo CaptainCore\Configurations::get()->name; ?> - Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
  <meta name="description" content="Manage your sites, billing, and account details.">
  <meta charset="utf-8">
<?php
$plugin_url = plugin_dir_url( __DIR__ );

// Load favicons and wpApiSettings from normal WordPress header
captaincore_header_content_extracted();

// Fetch current user details
$user = ( new CaptainCore\User )->profile();

if ( is_plugin_active( 'arve-pro/arve-pro.php' ) ) { ?>
<link rel='stylesheet' id='arve-main-css' href='/wp-content/plugins/advanced-responsive-video-embedder/build/main.css' type='text/css' media='all' />
<link rel='stylesheet' id='arve-pro-css' href='/wp-content/plugins/arve-pro/build/main.css' type='text/css' media='all' />
<?php } ?>
<link href="<?php echo home_url(); ?>/account/" rel="canonical">
<link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
<?php if ( substr( $_SERVER['SERVER_NAME'], -10) == '.localhost' ) { ?>
<link href="<?php echo $plugin_url; ?>public/css/vuetify.min.css" rel="stylesheet">
<link href="<?php echo $plugin_url; ?>public/css/materialdesignicons.min.css" rel="stylesheet">
<link href="<?php echo $plugin_url; ?>public/css/frappe-charts.min.css" rel="stylesheet">
<?php } else { ?>
<link href="https://cdn.jsdelivr.net/npm/vuetify@3.9.0/dist/vuetify.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@mdi/font@7.2.96/css/materialdesignicons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/frappe-charts@1.6.1/dist/frappe-charts.min.css" rel="stylesheet">
<?php } ?>
<link href="<?php echo $plugin_url; ?>public/css/captaincore-public-2025-12-19.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/themes/prism.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/themes/prism-twilight.min.css" rel="stylesheet" />
</head>
<body>
<div id="app" v-cloak>
	<v-app :style="{backgroundColor: 'rgb(var(--v-theme-accent))'}" :theme="theme">
	  <v-app-bar color="accent" density="compact" app flat class="pa-2">
		<v-list flat bg-color="transparent" :class="{ grow: route != 'login' && route != 'welcome' && route != 'connect' }" style="z-index: 10;">
		<v-list-item :href="configurations.path" @click.prevent="goToPath( '/' )" flat class="not-active">
			<template v-slot:prepend>
				<div style="width:40px;">
					<v-img :src="configurations.logo" :max-width="configurations.logo_width == '' ? 32 : configurations.logo_width" v-if="configurations.logo" class="pr-2"></v-img>
				</div>
			</template>
			<span v-show="configurations.logo_only != false">{{ configurations.name }}</span>
		</v-list-item>
		</v-list>
		<v-spacer></v-spacer>
		<template v-if="route != 'login' && route != 'welcome' && route != 'connect'">
		<div v-if="! isMobile" style="z-index: 1;">
		<v-tabs v-model="selected_nav">
			<v-tab class="pa-0" value="" style="display:none"></v-tab>
			<v-tab class="pa-0" value="sites" :href=`${configurations.path}sites` @click.prevent="goToPath( '/sites' )">Sites</v-tab>
			<v-tab class="pa-0" value="domains" :href=`${configurations.path}domains` @click.prevent="goToPath( '/domains' )">Domains</v-tab>
			<v-tab class="pa-0" value="accounts" :href=`${configurations.path}accounts` @click.prevent="goToPath( '/accounts' )">Accounts</v-tab>
			<v-tab class="pa-0" value="billing" :href=`${configurations.path}billing` @click.prevent="goToPath( '/billing' )" v-if="modules.billing">Billing</v-tab>
		</v-tabs>
		</div>
		<v-spacer></v-spacer>
		<div class="flex" style="opacity:0;"><textarea id="clipboard" style="height:1px;width:10px;display:flex;cursor:default"></textarea></div>
		<v-btn @click="toggleTheme" icon style="z-index: 10;">
            <v-icon>{{ theme === 'light' ? 'mdi-weather-sunny' : 'mdi-weather-night' }}</v-icon>
        </v-btn>
		<v-menu v-model="notifications" :close-on-content-click="false" content-class="elevation-0 v-sheet--outlined" offset-y rounded="xl">
		<template v-slot:activator="{ props }">
			<v-btn icon v-bind="props" v-show="route != 'login'" style="z-index: 10;">
			<v-badge dot color="error" :model-value="hasProviderActions">
				<v-icon>mdi-bell-ring</v-icon>
			</v-badge>
			</v-btn>
		</template>
		<v-card width="600">
			<v-list>
			<v-list-item>
				<v-list-item-title>Provider Activity</v-list-item-title>
			</v-list-item>
			</v-list>
			<v-divider></v-divider>
			<v-card flat v-show="provider_actions.length == 0">
				<v-card-text>
					<v-alert type="info" variant="tonal">There are no background activities.</v-alert>
				</v-card-text>
			</v-card>
			<v-list subheader lines="3">
			<v-list-item v-for="item in provider_actions">
				<v-list-item-title>{{ pretty_timestamp( item.created_at ) }}</v-list-item-title>
				<v-list-item-subtitle>{{ item.action.message }}</v-list-item-subtitle>
			</v-list-item>
			</v-list>
		</v-card>
	  </v-menu>
	  <v-menu location="bottom end" density="compact" rounded="lg">
		<template v-slot:activator="{ props }">
			<v-btn v-bind="props" variant="text" rounded="lg" style="z-index:10;height: 48px;">
			<v-avatar size="32" rounded="sm">
				<v-img :src="gravatar"></v-img>
			</v-avatar>
			<v-icon class="ml-1">mdi-chevron-down</v-icon>
			</v-btn>
		</template>

		<v-list min-width="240px" rounded="xl" density="compact" border="thin" class="elevation-0 text-body-2">
			<div class="body-2 mx-4 mb-1"><small>Welcome,</small><br />{{ current_user_display_name }}</div>
			<v-divider></v-divider>
			<v-list-subheader>Developers</v-list-subheader>
			<v-list-item link :href="`${configurations.path}cookbook`" @click.prevent="goToPath('/cookbook')" title="Cookbook" prepend-icon="mdi-code-tags"></v-list-item>
			<v-list-item link :href="`${configurations.path}vulnerability-scans`" @click.prevent="goToPath('/vulnerability-scans')" title="Vulnerability Scans" v-show="role == 'administrator'" prepend-icon="mdi-lock-open-alert"></v-list-item>
			<v-list-item link :href="`${configurations.path}health`" @click.prevent="goToPath('/health')" title="Health" prepend-icon="mdi-ladybug"></v-list-item>
			
			<v-list-subheader v-show="role == 'administrator' || role == 'owner'">Administrator</v-list-subheader>
			<v-list-item link :href="`${configurations.path}archives`" @click.prevent="goToPath('/archives')" title="Archives" v-show="role == 'administrator' || role == 'owner'" prepend-icon="mdi-folder-zip-outline"></v-list-item>
			<v-list-item link :href="`${configurations.path}configurations`" @click.prevent="goToPath('/configurations')" title="Configurations" v-show="role == 'administrator' || role == 'owner'" prepend-icon="mdi-cogs"></v-list-item>
			<v-list-item link :href="`${configurations.path}handbook`" @click.prevent="goToPath('/handbook')" title="Handbook" v-show="role == 'administrator' || role == 'owner'" prepend-icon="mdi-map"></v-list-item>
			<v-list-item link :href="`${configurations.path}defaults`" @click.prevent="goToPath('/defaults')" title="Site Defaults" v-show="role == 'administrator' || role == 'owner'" prepend-icon="mdi-application"></v-list-item>
			<v-list-item link :href="`${configurations.path}keys`" @click.prevent="goToPath('/keys')" title="SSH Keys" v-show="role == 'administrator' || role == 'owner'" prepend-icon="mdi-key"></v-list-item>
			<v-list-item link :href="`${configurations.path}subscriptions`" @click.prevent="goToPath('/subscriptions')" title="Subscriptions" v-show="role == 'administrator' && configurations.mode == 'hosting'" prepend-icon="mdi-repeat"></v-list-item>
			<v-list-item link :href="`${configurations.path}users`" @click.prevent="goToPath('/users')" title="Users" v-show="role == 'administrator' || role == 'owner'" prepend-icon="mdi-account-multiple"></v-list-item>
			
			<v-list-subheader>User</v-list-subheader>
			<v-list-item link :href="`${configurations.path}profile`" @click.prevent="goToPath('/profile')" title="Profile" prepend-icon="mdi-account-box"></v-list-item>
			<v-list-item link v-if="footer.switch_to_link" :href="footer.switch_to_link" :title="footer.switch_to_text" prepend-icon="mdi-logout"></v-list-item>
			<v-list-item link @click="signOut()" title="Log Out" prepend-icon="mdi-logout"></v-list-item>
		</v-list>
		</v-menu>
      </template>
      </v-app-bar>
	  <v-main class="mt-5">
		<div v-if="isMobile && ( route != 'login' && route != 'welcome' && route != 'connect' )">
		<v-tabs v-model="selected_nav" style="width: fit-content;margin: auto;">
			<v-tab value="" style="display:none"></v-tab>
			<v-tab value="sites" :href=`${configurations.path}sites` @click.prevent="goToPath( '/sites' )">Sites</v-tab>
			<v-tab value="domains" :href=`${configurations.path}domains` @click.prevent="goToPath( '/domains' )">Domains</v-tab>
			<v-tab value="accounts" :href=`${configurations.path}accounts` @click.prevent="goToPath( '/accounts' )">Accounts</v-tab>
			<v-tab value="billing" :href=`${configurations.path}billing` @click.prevent="goToPath( '/billing' )" v-if="modules.billing">Billing</v-tab>
		</v-tabs>
	  </div>
		<v-container class="px-0 pt-4 py-15">
		<v-dialog v-model="new_plugin.show" max-width="900px" scrollable>
		<v-card rounded="xl" height="700px">
			<v-toolbar flat color="primary">
			<v-btn icon dark @click.native="new_plugin.show = false">
				<v-icon>mdi-close</v-icon>
			</v-btn>
			<v-toolbar-title>
				Add plugin to 
				<span v-if="new_plugin.sites.length === 1">{{ new_plugin.sites[0].name }}</span>
				<span v-else>{{ new_plugin.sites.length }} sites</span>
			</v-toolbar-title>
			<v-spacer></v-spacer>
			</v-toolbar>
			<div class="flex-grow-0">
				<v-tabs v-model="new_plugin.tabs" bg-color="surface" color="primary" mandatory>
					<v-tab value="0">Upload</v-tab>
					<v-tab value="1">WordPress.org</v-tab>
					<v-tab value="2">Envato</v-tab>
				</v-tabs>
				<v-divider></v-divider>
			</div>
			
			<v-card-text class="pa-0" style="height: 100%; overflow-y: auto;">
			<v-window v-model="new_plugin.tabs" class="fill-height">
			<v-window-item value="0" :transition="false" :reverse-transition="false">
				<div class="upload-drag pt-4">
				<div class="upload">
					<div v-if="upload.length" class="mx-3">
					<div v-for="(file, index) in upload" :key="file.id">
						<span>{{ file.name }}</span> -
						<span>{{ formatSize( file.size ) }}</span> -
						<span v-if="file.error">{{file.error}}</span>
						<span v-else-if="file.success">success</span>
						<span v-else-if="file.active">active
						<v-progress-linear v-model="file.progress"></v-progress-linear>
						</span>
						<span v-else></span>
					</div>
					</div>
					<div v-else>
					<div class="text-center">
						<h4>Drop files anywhere to upload<br />or</h4>
						<label for="file" class="btn btn-lg btn-primary" style="padding: 0px 8px;">Select Files</label>
					</div>
					</div>
					<div v-show="$refs.upload && $refs.upload.dropActive" class="drop-active">
					<h3>Drop files to upload</h3>
					</div>
					<div class="upload-drag-btn">
					<file-upload class="btn btn-primary" @input-file="inputFile" post-action="<?php echo $plugin_url; ?>upload.php" :drop="true" v-model="upload" ref="upload"></file-upload>
					</div>
				</div>
				</div>
			</v-window-item>
			<v-window-item value="1" :transition="false" :reverse-transition="false" class="fill-height">
				<div style="position: sticky; top: 0; z-index: 2; background: rgb(var(--v-theme-surface));" class="pa-4 border-b">
					<v-row align="center" dense>
						<v-col cols="12" md="8">
							<v-text-field 
								variant="outlined" 
								density="compact" 
								hide-details 
								label="Search WordPress.org" 
								@click:append="new_plugin.search = $event.target.offsetParent.children[0].children[1].value; fetchPlugins()" 
								v-on:keyup.enter="new_plugin.search = $event.target.value; fetchPlugins()" 
								append-inner-icon="mdi-magnify" 
								:loading="new_plugin.loading">
							</v-text-field>
						</v-col>
						<v-col cols="12" md="4" class="d-flex justify-end">
							<v-pagination 
								v-if="new_plugin.api.info && new_plugin.api.info.pages > 1" 
								:length="new_plugin.api.info.pages - 1" 
								v-model="new_plugin.page" 
								:total-visible="5" 
								density="compact"
								rounded="circle"
								@update:model-value="fetchPlugins">
							</v-pagination>
						</v-col>
					</v-row>
				</div>
				
				<v-container fluid class="pa-4">
					<v-row>
						<v-col v-for="item in new_plugin.api.items" :key="item.slug" cols="12" sm="6" md="4">
							<v-card border flat height="100%" class="d-flex flex-column">
								<div class="d-flex pa-3">
									<v-avatar size="64" rounded="0" class="mr-3">
										<v-img :src='item.icons["1x"]' contain></v-img>
									</v-avatar>
									<div>
										<div class="text-subtitle-2 font-weight-bold" v-html="item.name" style="line-height: 1.2;"></div>
										<div class="text-caption text-medium-emphasis mt-1">Version {{ item.version }}</div>
									</div>
								</div>
								<v-card-text class="pt-0 text-caption text-truncate">
									{{ item.short_description }}
								</v-card-text>
								<v-spacer></v-spacer>
								<v-divider></v-divider>
								<v-card-actions>
									<v-spacer></v-spacer>
									<div v-if="new_plugin.current_plugins.includes( item.slug )">
										<v-btn size="small" variant="text" color="error" @click="uninstallPlugin( item )">Uninstall</v-btn>
										<v-btn size="small" variant="tonal" disabled>Installed</v-btn>
									</div>
									<v-btn v-else size="small" color="primary" variant="flat" @click="installPlugin( item )">Install</v-btn>
								</v-card-actions>
							</v-card>
						</v-col>
					</v-row>
				</v-container>
			</v-window-item>
			<v-window-item value="2" :transition="false" :reverse-transition="false" class="fill-height">
				<div style="position: sticky; top: 0; z-index: 2; background: rgb(var(--v-theme-surface));" class="pa-4 border-b">
					<v-text-field 
						variant="outlined" 
						density="compact" 
						hide-details 
						label="Search Envato Purchases" 
						v-model="new_plugin.envato.search" 
						append-inner-icon="mdi-magnify">
					</v-text-field>
				</div>
				<v-container fluid class="pa-4">
					<v-row>
						<v-col v-for="item in filteredEnvatoPlugins" :key="item.id" cols="12" sm="6" md="4">
							<v-card border flat height="100%" class="d-flex flex-column">
								<div class="d-flex pa-3">
									<v-avatar size="64" rounded="0" class="mr-3">
										<v-img :src='item.previews.icon_preview.icon_url' contain></v-img>
									</v-avatar>
									<div>
										<div class="text-subtitle-2 font-weight-bold" v-html="item.name" style="line-height: 1.2;"></div>
										<div class="text-caption text-medium-emphasis mt-1">ID: {{ item.id }}</div>
									</div>
								</div>
								<v-spacer></v-spacer>
								<v-divider></v-divider>
								<v-card-actions>
									<v-spacer></v-spacer>
									<v-btn size="small" color="primary" variant="flat" @click="installEnvatoPlugin( item )">Install</v-btn>
								</v-card-actions>
							</v-card>
						</v-col>
					</v-row>
				</v-container>
			</v-window-item>
			</v-window>
			</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="new_theme.show" max-width="900px" scrollable>
		<v-card rounded="xl" height="700px">
			<v-toolbar flat color="primary">
			<v-btn icon dark @click.native="new_theme.show = false">
				<v-icon>mdi-close</v-icon>
			</v-btn>
			<v-toolbar-title>
				Add theme to 
				<span v-if="new_theme.sites.length === 1">{{ new_theme.sites[0].name }}</span>
				<span v-else>{{ new_theme.sites.length }} sites</span>
			</v-toolbar-title>
			<v-spacer></v-spacer>
			</v-toolbar>
			<div class="flex-grow-0">
				<v-tabs v-model="new_theme.tabs" bg-color="surface" color="primary" mandatory>
					<v-tab value="0">Upload</v-tab>
					<v-tab value="1">WordPress.org</v-tab>
					<v-tab value="2">Envato</v-tab>
				</v-tabs>
				<v-divider></v-divider>
			</div>
			
			<v-card-text class="pa-0" style="height: 100%; overflow-y: auto;">
			<v-window v-model="new_theme.tabs" class="fill-height">
			<v-window-item value="0" :transition="false" :reverse-transition="false">
				<!-- Same upload HTML as plugin dialog -->
                <div class="upload-drag pt-4">
				<div class="upload">
					<div v-if="upload.length" class="mx-3">
					<div v-for="(file, index) in upload" :key="file.id">
						<span>{{ file.name }}</span> -
						<span>{{ formatSize( file.size ) }}</span> -
						<span v-if="file.error">{{file.error}}</span>
						<span v-else-if="file.success">success</span>
						<span v-else-if="file.active">active
						<v-progress-linear v-model="file.progress"></v-progress-linear>
						</span>
						<span v-else></span>
					</div>
					</div>
					<div v-else>
					<div class="text-center">
						<h4>Drop files anywhere to upload<br />or</h4>
						<label for="file" class="btn btn-lg btn-primary" style="padding: 0px 8px;">Select Files</label>
					</div>
					</div>
					<div v-show="$refs.upload && $refs.upload.dropActive" class="drop-active">
					<h3>Drop files to upload</h3>
					</div>
					<div class="upload-drag-btn">
					<file-upload class="btn btn-primary" @input-file="inputFile" post-action="<?php echo $plugin_url; ?>upload.php" :drop="true" v-model="upload" ref="upload"></file-upload>
					</div>
				</div>
				</div>
			</v-window-item>
			<v-window-item value="1" :transition="false" :reverse-transition="false" class="fill-height">
				<div style="position: sticky; top: 0; z-index: 2; background: rgb(var(--v-theme-surface));" class="pa-4 border-b">
					<v-row align="center" dense>
						<v-col cols="12" md="8">
							<v-text-field 
								variant="outlined" 
								density="compact" 
								hide-details 
								label="Search WordPress.org" 
								@click:append="new_theme.search = $event.target.offsetParent.children[0].children[1].value; fetchThemes()" 
								v-on:keyup.enter="new_theme.search = $event.target.value; fetchThemes()" 
								append-inner-icon="mdi-magnify" 
								:loading="new_theme.loading">
							</v-text-field>
						</v-col>
						<v-col cols="12" md="4" class="d-flex justify-end">
							<v-pagination 
								v-if="new_theme.api.info && new_theme.api.info.pages > 1" 
								:length="new_theme.api.info.pages - 1" 
								v-model="new_theme.page" 
								:total-visible="5" 
								density="compact"
								rounded="circle"
								@update:model-value="fetchThemes">
							</v-pagination>
						</v-col>
					</v-row>
				</div>
				
				<v-container fluid class="pa-4">
					<v-row>
						<v-col v-if="new_theme.api.items" v-for="item in new_theme.api.items" :key="item.slug" cols="12" sm="6" md="4">
							<v-card border flat height="100%" class="d-flex flex-column">
								<v-img :src="item.screenshot_url" height="150" cover></v-img>
								<div class="pa-3">
									<div class="text-subtitle-2 font-weight-bold" v-html="item.name" style="line-height: 1.2;"></div>
									<div class="text-caption text-medium-emphasis mt-1">Version {{ item.version }}</div>
								</div>
								<v-spacer></v-spacer>
								<v-divider></v-divider>
								<v-card-actions>
									<v-spacer></v-spacer>
								<v-divider></v-divider>
								<v-card-actions>
									<v-spacer></v-spacer>
									<div v-if="new_theme.current_themes && new_theme.current_themes.includes( item.slug )">
										<v-btn size="small" variant="text" color="error" @click="uninstallTheme( item )">Uninstall</v-btn>
										<v-btn size="small" variant="tonal" disabled>Installed</v-btn>
									</div>
									<v-btn v-else size="small" color="primary" variant="flat" @click="installTheme( item )">Install</v-btn>
								</v-card-actions>
							</v-card>
						</v-col>
					</v-row>
				</v-container>
			</v-window-item>
			<v-window-item value="2" :transition="false" :reverse-transition="false" class="fill-height">
				<div style="position: sticky; top: 0; z-index: 2; background: rgb(var(--v-theme-surface));" class="pa-4 border-b">
					<v-text-field 
						variant="outlined" 
						density="compact" 
						hide-details 
						label="Search Envato Purchases" 
						v-model="new_theme.envato.search" 
						append-inner-icon="mdi-magnify">
					</v-text-field>
				</div>
				<v-container fluid class="pa-4">
					<v-row>
						<v-col v-for="item in filteredEnvatoThemes" :key="item.id" cols="12" sm="6" md="4">
							<v-card border flat height="100%" class="d-flex flex-column">
								<div class="d-flex pa-3">
									<v-avatar size="64" rounded="0" class="mr-3">
										<v-img :src='item.previews.icon_preview.icon_url' contain></v-img>
									</v-avatar>
									<div>
										<div class="text-subtitle-2 font-weight-bold" v-html="item.name" style="line-height: 1.2;"></div>
										<div class="text-caption text-medium-emphasis mt-1">ID: {{ item.id }}</div>
									</div>
								</div>
								<v-spacer></v-spacer>
								<v-divider></v-divider>
								<v-card-actions>
									<v-spacer></v-spacer>
									<v-btn size="small" color="primary" variant="flat" @click="installEnvatoTheme( item )">Install</v-btn>
								</v-card-actions>
							</v-card>
						</v-col>
					</v-row>
				</v-container>
			</v-window-item>
			</v-window>
			</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="bulk_edit.show" max-width="600px">
		<v-card tile>
			<v-toolbar flat color="primary">
			<v-btn icon dark @click.native="bulk_edit.show = false">
				<v-icon>mdi-close</v-icon>
			</v-btn>
			<v-toolbar-title>Bulk edit on {{ bulk_edit.site_name }}</v-toolbar-title>
			<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text>
			<h3>Bulk edit {{ bulk_edit.items.length }} {{ bulk_edit.type }}</h3>
			<v-btn v-if="bulk_edit.type == 'plugins'" @click="bulkEditExecute('activate')">Activate</v-btn>
			<v-btn v-if="bulk_edit.type == 'plugins'" @click="bulkEditExecute('deactivate')">Deactivate</v-btn>
			<v-btn v-if="bulk_edit.type == 'plugins'" @click="bulkEditExecute('toggle')">Toggle</v-btn>
			<v-btn @click="bulkEditExecute('delete')">Delete</v-btn>
			</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_request_site.show" max-width="600px">
		<v-card flat>
			<v-toolbar flat color="primary">
			<v-btn icon dark @click.native="dialog_request_site.show = false">
				<v-icon>mdi-close</v-icon>
			</v-btn>
			<v-toolbar-title class="pl-2">Request a new WordPress site</v-toolbar-title>
			</v-toolbar>
			<v-card-text class="pt-4">
			<v-text-field :model-value="dialog_request_site.request.name" @update:model-value="dialog_request_site.request.name = $event" label="Name or Domain" hint="Please enter a name or domain name you wish to use for the new WordPress site." persistent-hint variant="underlined"></v-text-field>
			<v-autocomplete v-model="dialog_request_site.request.account_id" label="Account" :items="accounts" item-title="name" item-value="account_id" variant="underlined" auto-select-first></v-autocomplete>
			<v-textarea outlined persistent-hint :model-value="dialog_request_site.request.notes" @update:model-value="dialog_request_site.request.notes = $event" label="Notes" hint="Anything else you'd like to mention about this new site? (Optional)" persistent-hint variant="underlined"></v-textarea>
			<v-btn color="primary" class="pa-3 mt-4" @click="requestSite()">Request New Site</v-btn>
			</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_push_to_other.show" max-width="700px" scrollable>
            <v-card rounded="xl">
                <v-toolbar color="primary" flat>
                    <v-btn icon="mdi-close" @click="dialog_push_to_other.show = false"></v-btn>
                    <v-toolbar-title>Push {{ dialog_push_to_other.source_site?.name }} ({{ dialog_push_to_other.source_env?.environment }}) to...</v-toolbar-title>
                </v-toolbar>
                <v-card-text>
                    <v-text-field
                        v-model="dialog_push_to_other.search"
                        label="Search target sites/domains"
                        variant="underlined"
                        prepend-inner-icon="mdi-magnify"
                        clearable
                        hide-details
                        @update:model-value="dialog_push_to_other.currentPage = 1" class="mb-4"
                    ></v-text-field>

                    <v-progress-linear indeterminate v-if="dialog_push_to_other.loading"></v-progress-linear>

                    <v-alert type="info" variant="tonal" v-if="!dialog_push_to_other.loading && filteredPushTargets.length === 0"> No other Kinsta sites found for this provider account, or search yielded no results.</v-alert>

                    <v-list lines="two" v-if="!dialog_push_to_other.loading && paginatedPushTargets.length > 0">
                        <template v-for="(target, index) in paginatedPushTargets" :key="target.environment_id">
                            <v-list-item :subtitle="target.home_url">
                                <v-list-item-title>{{ target.site_name }} ({{ target.env_name }})</v-list-item-title>
                                <template v-slot:append>
                                    <v-btn
                                        size="small"
                                        color="primary"
                                        @click="confirmPushToOther(target)" variant="tonal"
                                        :disabled="isPushTargetSameAsSource(target)" >
                                        Push here
                                    </v-btn>
                                </template>
                            </v-list-item>
                             <v-divider v-if="index < paginatedPushTargets.length - 1" class="my-1"></v-divider> </template>
                    </v-list>
                    <v-pagination
                        v-if="!dialog_push_to_other.loading && totalPagesPushTargets > 1"
                        v-model="dialog_push_to_other.currentPage"
                        :length="totalPagesPushTargets"
                        :total-visible="7"
                        density="compact"
                        class="mt-4"
                    ></v-pagination>
                </v-card-text>
            </v-card>
        </v-dialog>
        <v-dialog v-model="dialog_push_to_other.confirm.show" max-width="500px">
            <v-card rounded="xl">
                <v-toolbar color="warning" flat>
                    <v-toolbar-title>Confirm Push Operation</v-toolbar-title>
                </v-toolbar>
                <v-card-text class="text-body-1 pa-4">
                    Are you sure you want to push
                    <br/><strong>{{ dialog_push_to_other.source_site?.name }} ({{ dialog_push_to_other.source_env?.environment }})</strong>
                    <br/>to overwrite
                    <br/><strong>{{ dialog_push_to_other.target_site?.name }} ({{ dialog_push_to_other.target_env?.name }})</strong>
                    <br/>at <a :href="'//' + dialog_push_to_other.target_env?.home_url" target="_blank">{{ dialog_push_to_other.target_env?.home_url }}</a>?
                    <br/><br/>
                    <strong class="text-error">This action cannot be undone.</strong> It will replace both the files and database of the target environment.
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="dialog_push_to_other.confirm.show = false">Cancel</v-btn>
                    <v-btn color="warning" @click="executePushToOther()">Confirm Push</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
		<v-dialog v-model="dialog_mailgun_deploy.show" max-width="700px" scrollable>
			<v-card rounded="xl">
				<v-toolbar color="primary" flat>
					<v-btn icon="mdi-close" @click="dialog_mailgun_deploy.show = false"></v-btn>
					<v-toolbar-title>Deploy to Connected Site</v-toolbar-title>
				</v-toolbar>
				<v-card-text>
					<v-text-field
						v-model="dialog_mailgun_deploy.search"
						label="Search connected sites..."
						variant="underlined"
						prepend-inner-icon="mdi-magnify"
						clearable
						hide-details
						@update:model-value="dialog_mailgun_deploy.currentPage = 1" class="mb-4"
					></v-text-field>

					<v-alert type="info" variant="tonal" v-if="filteredMailgunDeployTargets.length === 0">
						No connected sites found matching your search.
					</v-alert>

					<v-list lines="two" v-if="paginatedMailgunDeployTargets.length > 0">
						<template v-for="(site, index) in paginatedMailgunDeployTargets" :key="site.id + site.environment">
							<v-list-item :subtitle="site.home_url">
								<v-list-item-title>{{ site.name }} ({{ site.environment }})</v-list-item-title>
								<template v-slot:append>
									<v-btn
										size="small"
										variant="tonal"
										@click="magicLoginForDeployTarget(site)"
										class="mr-2"
									>
										Login
									</v-btn>
									<v-btn
										size="small"
										color="primary"
										@click="showMailgunDeployPrompt(site, dialog_domain); dialog_mailgun_deploy.show = false"
										variant="tonal"
									>
										Deploy
									</v-btn>
								</template>
							</v-list-item>
								<v-divider v-if="index < paginatedMailgunDeployTargets.length - 1" class="my-1"></v-divider>
						</template>
					</v-list>
					
					<v-pagination
						v-if="totalPagesMailgunDeployTargets > 1"
						v-model="dialog_mailgun_deploy.currentPage"
						:length="totalPagesMailgunDeployTargets"
						:total-visible="7"
						density="compact"
						class="mt-4"
					></v-pagination>
				</v-card-text>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_domain_mappings.show" max-width="900px" scrollable>
		<v-card>
			<v-card-title class="headline d-flex justify-space-between">
			<span>Domain Mappings</span>
			<v-btn icon variant="text" @click="fetchDomains(dialog_domain_mappings.site_id)">
				<v-icon>mdi-refresh</v-icon>
			</v-btn>
			</v-card-title>

			<v-divider></v-divider>

			<v-card-text style="height: 400px;">

				<v-alert 
					v-if="dialog_domain_mappings.errors.length > 0" 
					type="error" 
					variant="tonal" 
					class="mt-4"
					closable
					@click:close="dialog_domain_mappings.errors = []"
				>
					<div v-for="error in dialog_domain_mappings.errors" :key="error">{{ error }}</div>
				</v-alert>
			
				<v-data-table
					:headers="[
						{ title: 'Domain Name', key: 'name', align: 'start' },
						{ title: 'Status', key: 'status', width: '120px' },
						{ title: 'Primary', key: 'primary', align: 'center', width: '100px', sortable: false },
						{ title: 'Actions', key: 'actions', align: 'end', sortable: false, width: '150px' }
					]"
					:items="dialog_domain_mappings.domains"
					:items-per-page="-1"
					:loading="dialog_domain_mappings.loading"
					hide-default-footer
					density="comfortable"
					class="elevation-0 mt-2"
				>
					<template v-slot:no-data>
						<div class="text-center pa-4 text-grey">
							No domains mapped yet. Add one below.
						</div>
					</template>

					<template v-slot:item.name="{ item }">
						<strong>{{ item.name }}</strong>
					</template>

					<template v-slot:item.status="{ item }">
						<v-chip v-if="item.is_active" color="success" size="small" label variant="tonal">Active</v-chip>
						<v-chip v-else color="warning" size="small" label variant="tonal">Pending</v-chip>
					</template>

					<template v-slot:item.primary="{ item }">
						<v-icon v-if="item.name.includes('kinsta.cloud')" color="grey lighten-2" title="System Domain">mdi-cloud</v-icon>
						<v-icon v-else-if="dialog_site.environment_selected.home_url.includes(item.name)" color="amber" title="Primary Domain">mdi-star</v-icon>
						<v-tooltip location="top" v-else>
							<template v-slot:activator="{ props }">
								<v-btn v-bind="props" icon="mdi-star-outline" size="small" variant="text" color="grey" @click="setPrimaryDomainMapping(item)"></v-btn>
							</template>
							<span>Set as Primary</span>
						</v-tooltip>
					</template>

					<template v-slot:item.actions="{ item }">
						<v-btn 
							v-if="!item.is_active && item.verification_records && item.verification_records.length > 0" 
							size="small" color="primary" variant="tonal" class="mr-2" 
							@click="openVerificationModal(item)"
						>Verify</v-btn>
						<v-btn icon="mdi-delete" size="small" color="error" variant="text" @click="deleteDomainMapping(item)"></v-btn>
					</template>
				</v-data-table>

				<div class="d-flex align-center mt-4 pt-2" style="border-top: 1px solid rgba(0,0,0,0.12);">
					<v-text-field
						v-model="dialog_domain_mappings.new_domain"
						label="Enter domain name (e.g. example.com)"
						variant="underlined"
						hide-details
						class="mr-4"
						@keydown.enter="addDomainMapping"
						:disabled="dialog_domain_mappings.loading"
					></v-text-field>
					<v-btn 
						color="primary" 
						@click="addDomainMapping"
						:loading="dialog_domain_mappings.loading"
						:disabled="!dialog_domain_mappings.new_domain"
						prepend-icon="mdi-plus"
					>
						Add Domain
					</v-btn>
				</div>

			</v-card-text>

			<v-divider></v-divider>

			<v-card-actions>
			<v-spacer></v-spacer>
			<v-btn color="primary" text @click="dialog_domain_mappings.show = false">Close</v-btn>
			</v-card-actions>
		</v-card>
			<v-dialog v-model="verificationDialog.show" max-width="600px">
				<v-card v-if="verificationDialog.domain">
					<v-toolbar color="primary" density="compact" flat>
						<v-toolbar-title class="text-white">
							Verify {{ verificationDialog.domain.name }}
						</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-btn icon="mdi-close" variant="text" color="white" @click="verificationDialog.show = false"></v-btn>
					</v-toolbar>
					
					<v-card-text class="pt-4">
						<p class="mb-4">Please add the following DNS records to your domain provider (Cloudflare, GoDaddy, Namecheap, etc.) to prove ownership.</p>
						
						<v-table 
							density="compact" 
							class="elevation-1 mb-4" 
							v-if="verificationDialog.domain && verificationDialog.domain.verification_records && verificationDialog.domain.verification_records.length > 0"
						>
							<thead>
								<tr>
									<th>Type</th>
									<th>Name / Host</th>
									<th class="text-right">Value</th>
								</tr>
							</thead>
							<tbody>
								<template v-for="(record, i) in verificationDialog.domain.verification_records" :key="i">
									<tr v-if="record">
										<td><strong>{{ record.type }}</strong></td>
										<td>
											{{ record.name }}
											<v-btn icon size="x-small" variant="text" @click="copyText(record.name)">
												<v-icon size="small">mdi-content-copy</v-icon>
											</v-btn>
										</td>
										<td class="text-right">
											<span style="max-width: 200px; display: inline-block; overflow: hidden; text-overflow: ellipsis; vertical-align: middle;">
												{{ record.value }}
											</span>
											<v-btn icon size="x-small" variant="text" @click="copyText(record.value)">
												<v-icon size="small">mdi-content-copy</v-icon>
											</v-btn>
										</td>
									</tr>
								</template>
							</tbody>
						</v-table>
						<v-alert type="info" density="compact" variant="tonal" icon="mdi-clock-outline">
							DNS changes may take a few minutes to propagate.
						</v-alert>
					</v-card-text>

					<v-divider></v-divider>

					<v-card-actions>
						<v-spacer></v-spacer>
						<v-btn variant="text" @click="verificationDialog.show = false">Cancel</v-btn>
						<v-btn 
							color="primary" 
							:loading="verificationDialog.loading" 
							@click="attemptVerification"
						>
							Attempt to Verify
						</v-btn>
					</v-card-actions>
				</v-card>
			</v-dialog>
		</v-dialog>
		<v-dialog v-model="dialog_domain.confirm_mx_overwrite" max-width="500px" persistent>
			<v-card>
				<v-toolbar color="warning" flat>
					<v-toolbar-title>Confirm MX Record Overwrite</v-toolbar-title>
				</v-toolbar>
				<v-card-text class="text-body-1">
					This domain already has existing MX records. Activating email forwarding will
					<strong>delete all existing MX records</strong>
					and replace them with the ones required by Forward Email.
					<br><br>
					Are you sure you want to proceed?
				</v-card-text>
				<v-card-actions>
					<v-spacer></v-spacer>
					<v-btn variant="text" @click="dialog_domain.confirm_mx_overwrite = false">Cancel</v-btn>
					<v-btn color="warning" @click="activateEmailForwarding(true)">Confirm Overwrite</v-btn>
				</v-card-actions>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_domain.update_account.show" max-width="500px">
			<v-card>
				<v-toolbar color="primary" dark>
					<v-btn icon="mdi-close" @click="dialog_domain.update_account.show = false"></v-btn>
					<v-toolbar-title>Update Domain Account</v-toolbar-title>
				</v-toolbar>
				<v-card-text>
					<v-autocomplete
						v-model="dialog_domain.update_account.site"
						:items="sites"
						item-title="name"
						item-value="site_id"
						label="Link to different site"
						return-object
						hint="The domain's billing account will be linked to this site's customer account."
						persistent-hint
						class="mt-5"
						spellcheck="false"
						flat
						variant="underlined"
					></v-autocomplete>
				</v-card-text>
				<v-card-actions class="justify-end">
					<v-btn color="primary" variant="outlined" @click="updateDomainSiteLink()">
						Update
					</v-btn>
				</v-card-actions>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_fathom.show" max-width="500px">
		<v-card tile>
			<v-toolbar flat dark color="primary">
				<v-btn icon="mdi-close" dark @click.native="dialog_fathom.show = false"></v-btn>
				<v-toolbar-title>Configure Fathom for {{ dialog_fathom.site.name }}</v-toolbar-title>
			</v-toolbar>
			<v-card-text class="pt-3">
				<v-progress-linear indeterminate v-if="dialog_fathom.loading"></v-progress-linear>
				<p class="mb-0">Fathom Analytics</p>
				<table style="width: 100%">
					<tr v-for="tracker in dialog_site.environment_selected.fathom_analytics" :key="tracker.code">
					<td>
						<v-text-field variant="underlined" v-model="tracker.domain" label="Domain" hide-details></v-text-field>
					</td>
					<td>
						<v-text-field variant="underlined" v-model="tracker.code" label="Code" hide-details></v-text-field>
					</td>
					<td style="width:32px;">
						<v-icon @click="deleteFathomItem(tracker)">mdi-delete</v-icon>
					</td>
					</tr>
				</table>
				<v-row>
					<v-col cols="12" class="text-right">
					<v-btn
						icon="mdi-plus"
						size="small"
						@click='dialog_site.environment_selected.fathom_analytics.push({ "code": "", "domain" : "" })'
					></v-btn>
					</v-col>
				</v-row>
				<p class="mb-0 mt-4">Fathom Lite</p>
				<table style="width: 100%">
					<tr v-for="tracker in dialog_fathom.environment.fathom" :key="tracker.code">
					<td>
						<v-text-field variant="underlined" v-model="tracker.domain" label="Domain" hide-details></v-text-field>
					</td>
					<td>
						<v-text-field variant="underlined" v-model="tracker.code" label="Code" hide-details></v-text-field>
					</td>
					<td style="width:32px;">
						<v-icon @click="deleteFathomLiteItem(tracker)">mdi-delete</v-icon>
					</td>
					</tr>
				</table>
				<v-row>
					<v-col cols="12" class="text-right">
					<v-btn icon="mdi-plus" size="small" @click="newFathomItem"></v-btn>
					</v-col>
				</v-row>

				<v-btn color="primary" dark @click="saveFathomConfigurations()">
					Save Fathom configurations
				</v-btn>
			</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_fathom.editItem" max-width="500px">
			<v-card>
				<v-card-title>
				<span class="text-h5">Edit Item</span>
				</v-card-title>
				<v-card-text>
				<v-container>
					<v-row>
					<v-col cols="12" sm="6">
						<v-text-field v-model="dialog_fathom.editedItem.domain" label="Domain"></v-text-field>
					</v-col>
					<v-col cols="12" sm="6">
						<v-text-field v-model="dialog_fathom.editedItem.code" label="Code"></v-text-field>
					</v-col>
					</v-row>
				</v-container>
				</v-card-text>
				<v-card-actions>
				<v-spacer></v-spacer>
				<v-btn color="blue-darken-1" variant="text" @click="configureFathomClose">Cancel</v-btn>
				<v-btn color="blue-darken-1" variant="text" @click="configureFathomSave">Save</v-btn>
				</v-card-actions>
			</v-card>
	  </v-dialog>
	  <v-dialog v-model="new_recipe.show" max-width="800px">
	  <v-card rounded="0">
		<v-toolbar elevation="0" color="primary">
			<v-btn icon="mdi-close" @click="new_recipe.show = false"></v-btn>
			<v-toolbar-title>New Recipe</v-toolbar-title>
		</v-toolbar>
		<v-card-text>
			<v-container>
			<v-row>
				<v-col cols="12">
				<v-text-field variant="underlined" label="Name" v-model="new_recipe.title"></v-text-field>
				</v-col>
				<v-col cols="12">
				<v-textarea variant="underlined" label="Content" persistent-hint hint="Bash script and WP-CLI commands welcomed." auto-grow v-model="new_recipe.content" spellcheck="false"></v-textarea>
				</v-col>
				<v-col cols="12" v-if="role == 'administrator' || role == 'owner'">
				<v-switch label="Public" v-model="new_recipe.public" persistent-hint hint="Public by default. Turning off will make the recipe only viewable and useable by you." :false-value="0" :true-value="1" inset color="primary"></v-switch>
				</v-col>
				<v-col cols="12" class="text-end pa-0 ma-0">
				<v-btn color="primary" @click="addRecipe()"> Add New Recipe </v-btn>
				</v-col>
			</v-row>
			</v-container>
		</v-card-text>
	  </v-card>
	  </v-dialog>
	  <v-dialog v-model="dialog_new_account.show" max-width="800px" persistent scrollable v-if="role == 'administrator'">
	  <v-card tile>
		<v-toolbar flat color="primary">
			<v-btn icon="mdi-close" @click.native="dialog_new_account.show = false"></v-btn>
			<v-toolbar-title>New Account</v-toolbar-title>
		</v-toolbar>
		<v-card-text>
			<v-container>
			<v-row>
				<v-col cols="12" pa-2>
				<v-text-field variant="underlined" label="Name" :model-value="dialog_new_account.name" @update:model-value="dialog_new_account.name = $event"></v-text-field>
				</v-col>
				<v-col cols="12" text-right pa-0 ma-0>
				<v-btn color="primary" dark @click="createSiteAccount()">
					Create Account
				</v-btn>
				</v-col>
			</v-row>
			</v-container>
		</v-card-text>
		</v-card>
	  </v-dialog>
	  <v-dialog v-model="dialog_account_portal.show" max-width="800px" persistent scrollable>
      <v-card rounded="0">
            <v-toolbar color="primary" flat>
                <v-btn icon="mdi-close" @click="dialog_account_portal.show = false">
                </v-btn>
                <v-toolbar-title>Account Portal</v-toolbar-title>
                <v-spacer></v-spacer>
            </v-toolbar>
            <v-card-text style="max-height: 100%;" class="mt-3">
                <v-row>
                    <v-col class="pb-0">
                        <v-text-field label="Domain" :model-value="dialog_account_portal.portal.domain" @update:model-value="dialog_account_portal.portal.domain = $event" variant="underlined"></v-text-field>
                    </v-col>
                </v-row>
                <v-row>
                    <v-col cols="12" md="6" class="py-0">
                        <v-text-field v-model="dialog_account_portal.portal.name" label="Name" variant="underlined"></v-text-field>
                        <v-switch v-model="dialog_account_portal.portal.logo_only" label="Show only logo" color="primary" inset hide-details></v-switch>
                    </v-col>
                    <v-col cols="12" md="6" class="py-0">
                        <v-text-field v-model="dialog_account_portal.portal.url" label="URL" variant="underlined"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6" class="py-0">
                        <v-text-field v-model="dialog_account_portal.portal.logo" label="Logo URL" variant="underlined"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6" class="py-0">
                        <v-text-field v-model="dialog_account_portal.portal.logo_width" label="Logo Width" variant="underlined"></v-text-field>
                    </v-col>
                </v-row>
                    <span class="text-body-2">DNS Labels</span>
                    <v-row>
                        <v-col cols="9">
                            <v-textarea v-model="dialog_account_portal.portal.dns_introduction" label="Introduction" auto-grow rows="3" variant="underlined"></v-textarea>
                        </v-col>
                        <v-col cols="3">
                            <v-textarea v-model="dialog_account_portal.portal.dns_nameservers" label="Nameservers" spellcheck="false" auto-grow variant="underlined"></v-textarea>
                        </v-col>
                    </v-row>
                        <span class="text-body-2">Theme colors</span>
                    <v-row>
                        <v-col class="shrink" style="min-width: 172px;">
                            <v-text-field persistent-hint hint="Primary" v-model="dialog_account_portal.portal.colors.primary" class="ma-0 pa-0" variant="solo">
                            <template v-slot:append-inner>
                                <v-menu v-model="dialog_account_portal.colors.primary" location="bottom" :close-on-content-click="false">
                                    <template v-slot:activator="{ props }">
                                        <div :style="{ backgroundColor: dialog_account_portal.portal.colors.primary, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
                                    </template>
                                    <v-card>
                                        <v-card-text class="pa-0">
                                            <v-color-picker v-model="dialog_account_portal.portal.colors.primary"></v-color-picker>
                                        </v-card-text>
                                    </v-card>
                                </v-menu>
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col class="shrink" style="min-width: 172px;">
                            <v-text-field persistent-hint hint="Secondary" v-model="dialog_account_portal.portal.colors.secondary" class="ma-0 pa-0" variant="solo">
                            <template v-slot:append-inner>
                                <v-menu v-model="dialog_account_portal.colors.secondary" location="bottom" :close-on-content-click="false">
                                    <template v-slot:activator="{ props }">
                                        <div :style="{ backgroundColor: dialog_account_portal.portal.colors.secondary, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
                                    </template>
                                    <v-card>
                                        <v-card-text class="pa-0">
                                            <v-color-picker v-model="dialog_account_portal.portal.colors.secondary"></v-color-picker>
                                        </v-card-text>
                                    </v-card>
                                </v-menu>
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col class="shrink" style="min-width: 172px;">
                            <v-text-field persistent-hint hint="Accent" v-model="dialog_account_portal.portal.colors.accent" class="ma-0 pa-0" variant="solo">
                            <template v-slot:append-inner>
                                <v-menu v-model="dialog_account_portal.colors.accent" location="bottom" :close-on-content-click="false">
                                    <template v-slot:activator="{ props }">
                                        <div :style="{ backgroundColor: dialog_account_portal.portal.colors.accent, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
                                    </template>
                                    <v-card>
                                        <v-card-text class="pa-0">
                                            <v-color-picker v-model="dialog_account_portal.portal.colors.accent"></v-color-picker>
                                        </v-card-text>
                                    </v-card>
                                </v-menu>
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col class="shrink" style="min-width: 172px;">
                            <v-text-field persistent-hint hint="Error" v-model="dialog_account_portal.portal.colors.error" class="ma-0 pa-0" variant="solo">
                            <template v-slot:append-inner>
                                <v-menu v-model="dialog_account_portal.colors.error" location="bottom" :close-on-content-click="false">
                                    <template v-slot:activator="{ props }">
                                        <div :style="{ backgroundColor: dialog_account_portal.portal.colors.error, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
                                    </template>
                                    <v-card>
                                        <v-card-text class="pa-0">
                                            <v-color-picker v-model="dialog_account_portal.portal.colors.error"></v-color-picker>
                                        </v-card-text>
                                    </v-card>
                                </v-menu>
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col class="shrink" style="min-width: 172px;">
                            <v-text-field persistent-hint hint="Info" v-model="dialog_account_portal.portal.colors.info" class="ma-0 pa-0" variant="solo">
                            <template v-slot:append-inner>
                                <v-menu v-model="dialog_account_portal.colors.info" location="bottom" :close-on-content-click="false">
                                    <template v-slot:activator="{ props }">
                                        <div :style="{ backgroundColor: dialog_account_portal.portal.colors.info, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
                                    </template>
                                    <v-card>
                                        <v-card-text class="pa-0">
                                            <v-color-picker v-model="dialog_account_portal.portal.colors.info"></v-color-picker>
                                        </v-card-text>
                                    </v-card>
                                </v-menu>
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col class="shrink" style="min-width: 172px;">
                            <v-text-field persistent-hint hint="Success" v-model="dialog_account_portal.portal.colors.success" class="ma-0 pa-0" variant="solo">
                            <template v-slot:append-inner>
                                <v-menu v-model="dialog_account_portal.colors.success" location="bottom" :close-on-content-click="false">
                                    <template v-slot:activator="{ props }">
                                        <div :style="{ backgroundColor: dialog_account_portal.portal.colors.success, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
                                    </template>
                                    <v-card>
                                        <v-card-text class="pa-0">
                                            <v-color-picker v-model="dialog_account_portal.portal.colors.success"></v-color-picker>
                                        </v-card-text>
                                    </v-card>
                                </v-menu>
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col class="shrink" style="min-width: 172px;">
                            <v-text-field persistent-hint hint="Warning" v-model="dialog_account_portal.portal.colors.warning" class="ma-0 pa-0" variant="solo">
                            <template v-slot:append-inner>
                                <v-menu v-model="dialog_account_portal.colors.warning" location="bottom" :close-on-content-click="false">
                                    <template v-slot:activator="{ props }">
                                        <div :style="{ backgroundColor: dialog_account_portal.portal.colors.warning, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
                                    </template>
                                    <v-card>
                                        <v-card-text class="pa-0">
                                            <v-color-picker v-model="dialog_account_portal.portal.colors.warning"></v-color-picker>
                                        </v-card-text>
                                    </v-card>
                                </v-menu>
                            </template>
                            </v-text-field>
                        </v-col>
                    </v-row>
                    <v-row>
                        <v-col><v-btn @click="resetPortalColors">Reset colors</v-btn></v-col>
                    </v-row>
                    <span class="text-body-2">Email Configurations</span>
                    <v-row>
                        <v-col><v-text-field v-model="dialog_account_portal.portal.email.encryption_type" variant="underlined"></v-text-field></v-col>
                        <v-col><v-text-field v-model="dialog_account_portal.portal.email.host" variant="underlined"></v-text-field></v-col>
                        <v-col><v-text-field v-model="dialog_account_portal.portal.email.auth" variant="underlined"></v-text-field></v-col>
                        <v-col><v-text-field v-model="dialog_account_portal.portal.email.port" variant="underlined"></v-text-field></v-col>
                    </v-row>
                <div class="d-flex justify-end pa-0 ma-0">
                    <v-btn color="primary" @click="updateAccountPortal()">
                        Update Account Portal
                    </v-btn>
                </div>
            </v-card-text>
        </v-card>
      </v-dialog>
	  <v-dialog v-model="dialog_edit_account.show" max-width="800px" persistent scrollable>
	  <v-card tile>
		<v-toolbar color="primary" dark flat>
			<v-btn icon="mdi-close" @click.native="dialog_edit_account.show = false"></v-btn>
			<v-toolbar-title>Edit Account</v-toolbar-title>
			<v-spacer></v-spacer>
		</v-toolbar>
		<v-card-text>
			<v-container>
			<v-row>
				<v-col cols="12" pa-2>
					<v-text-field variant="underlined" hide-details label="Name" :model-value="dialog_edit_account.account.name" @update:model-value="dialog_edit_account.account.name = $event"></v-text-field>
				</v-col>
				<v-col cols="12" pa-2>
					<v-autocomplete variant="underlined" hide-details label="Account Portal" item-title="domain" item-value="account_portal_id" v-model="dialog_edit_account.account.account_portal_id" :items="accountportals"></v-autocomplete>
				</v-col>
				<v-col cols="12" text-right pa-0 ma-0>
				<v-btn color="primary" @click="updateSiteAccount()">
					Save Account
				</v-btn>
				</v-col>
			</v-row>
			</v-container>
		</v-card-text>
		</v-card>

	  </v-dialog>
	  <v-dialog v-model="dialog_cookbook.show" max-width="800px" persistent scrollable>
		<v-card tile>
			<v-toolbar flat>
			<v-btn icon @click="dialog_cookbook.show = false"><v-icon>mdi-close</v-icon></v-btn>
			<v-toolbar-title>Edit Recipe</v-toolbar-title>
			<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text style="max-height: 100%;">
			<v-container>
				<v-row>
				<v-col cols="12"><v-text-field variant="underlined" label="Name" v-model="dialog_cookbook.recipe.title"></v-text-field></v-col>
				<v-col cols="12"><v-textarea variant="underlined" label="Content" persistent-hint hint="Bash script and WP-CLI commands welcomed." auto-grow v-model="dialog_cookbook.recipe.content" spellcheck="false"></v-textarea></v-col>
				<v-col cols="12" v-if="role == 'administrator' || role == 'owner'"><v-switch label="Public" v-model="dialog_cookbook.recipe.public" persistent-hint hint="Public by default. Turning off will make the recipe only viewable and useable by you." :false-value="0" :true-value="1" inset></v-switch></v-col>
				<v-col cols="12" class="text-right">
					<v-btn color="error" elevation="0" @click="deleteRecipe()" class="mx-3">Delete Recipe</v-btn>
					<v-btn color="primary" elevation="0" @click="updateRecipe()">Save Recipe</v-btn>
				</v-col>
				</v-row>
			</v-container>
			</v-card-text>
		</v-card>
	  </v-dialog>
	  <v-dialog v-model="dialog_user.show" max-width="800px" persistent scrollable>
		<v-card v-if="typeof dialog_user.user == 'object'">
			<v-toolbar density="compact">
				<v-btn icon @click="dialog_user.show = false">
					<v-icon>mdi-close</v-icon>
				</v-btn>
				<v-toolbar-title>Edit user {{ dialog_user.user.name }}</v-toolbar-title>
				<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text>
				<v-col cols="12" class="pa-2">
					<v-text-field label="Name" v-model="dialog_user.user.name" variant="underlined"></v-text-field>
				</v-col>
				<v-col cols="12" class="pa-2">
					<v-text-field label="Email" v-model="dialog_user.user.email" variant="underlined"></v-text-field>
				</v-col>
				<v-autocomplete 
					:items="accounts" 
					item-title="name" 
					item-value="account_id" 
					v-model="dialog_user.user.account_ids" 
					label="Accounts" 
					chips 
					multiple 
					closable-chips
					variant="underlined"
				></v-autocomplete>
				<v-alert variant="tonal" type="error" v-for="error in dialog_user.errors" class="mt-5">{{ error }}</v-alert>
				<v-col cols="12" class="text-right pa-0 ma-0">
					<v-btn color="primary" dark @click="saveUser()">
						Save User
					</v-btn>
				</v-col>
			</v-card-text>
		</v-card>
	  </v-dialog>
	  <v-dialog v-model="new_key.show" max-width="800px" v-if="role == 'administrator' || role == 'owner'">
		<v-card rounded="0" style="margin:auto;max-width:800px">
		<v-toolbar>
			<v-btn icon="mdi-close" @click="new_key.show = false"></v-btn>
			<v-toolbar-title>New Management SSH Key</v-toolbar-title>
			<v-spacer></v-spacer>
		</v-toolbar>
		<v-card-text style="max-height: 100%;">
		<v-container>
			<v-row>
				<v-col cols="12" class="pa-2">
					<v-text-field label="Name" :model-value="new_key.title" @update:model-value="new_key.title = $event" variant="underlined"></v-text-field>
				</v-col>
				<v-col cols="12" class="pa-2">
					<v-textarea label="Private Key" persistent-hint hint="Contents of your private key file. Typically named something like 'id_rsa'. The corresponding public key will need to added to your host provider." auto-grow :model-value="new_key.key" @update:model-value="new_key.key = $event" spellcheck="false" variant="underlined"></v-textarea>
				</v-col>
				<v-col cols="12" class="text-right pa-0 ma-0">
					<v-btn color="primary" @click="addNewKey()">
						Add New SSH Key
					</v-btn>
				</v-col>
			</v-row>
		</v-container>
		</v-card-text>
		</v-card>
	</v-dialog>

	<v-dialog v-model="new_key_user.show" max-width="800px">
		<v-card rounded="0" style="margin:auto;max-width:800px">
		<v-toolbar>
			<v-btn icon="mdi-close" @click="new_key_user.show = false"></v-btn>
			<v-toolbar-title>New SSH Key</v-toolbar-title>
			<v-spacer></v-spacer>
		</v-toolbar>
		<v-card-text style="max-height: 100%;">
		<v-container>
			<v-row>
				<v-col cols="12" class="pa-2">
					<v-text-field label="Name" :model-value="new_key_user.title" @update:model-value="new_key_user.title = $event" variant="underlined"></v-text-field>
				</v-col>
				<v-col cols="12" class="pa-2">
					<v-textarea label="Public Key" persistent-hint hint="Contents of your public key file. Typically found in '~/.ssh/id_rsa.pub'." auto-grow :model-value="new_key_user.key" @update:model-value="new_key_user.key = $event" spellcheck="false" variant="underlined"></v-textarea>
				</v-col>
				<v-col cols="12" class="text-right pa-0 ma-0">
					<v-btn color="primary" @click="addNewKey()">
						Add New SSH Key
					</v-btn>
				</v-col>
			</v-row>
		</v-container>
		</v-card-text>
		</v-card>
	</v-dialog>

	<v-dialog v-model="dialog_key.show" v-if="role == 'administrator' || role == 'owner'" max-width="800px" persistent scrollable>
		<v-card rounded="0">
		<v-toolbar>
			<v-btn icon="mdi-close" @click="dialog_key.show = false"></v-btn>
			<v-toolbar-title>Edit SSH Key</v-toolbar-title>
			<v-spacer></v-spacer>
			<v-toolbar-items v-show="dialog_key.key.main == '0'">
				<v-btn variant="text" @click="setKeyAsPrimary()" color="primary">Set as Primary Key</v-btn>
			</v-toolbar-items>
		</v-toolbar>
		<v-card-text style="max-height: 100%;">
			<v-container>
			<v-row>
				<v-col cols="12">
					Key Fingerprint<br />
					{{ dialog_key.key.fingerprint }}
				</v-col>
				<v-col cols="12">
					<v-text-field label="Name" :model-value="dialog_key.key.title" @update:model-value="dialog_key.key.title = $event" variant="underlined"></v-text-field>
				</v-col>
				<v-col cols="12">
					<v-textarea label="Private Key" persistent-hint hint="Enter new private key to override existing key. The current key is not viewable." auto-grow :model-value="dialog_key.key.key" @update:model-value="dialog_key.key.key = $event" spellcheck="false" variant="underlined"></v-textarea>
				</v-col>
			</v-row>
			<v-row>
				<v-col cols="12" class="text-right">
					<v-btn @click="deleteKey()" class="mr-2">
						Delete SSH Key
					</v-btn>
					<v-btn color="primary" @click="updateKey()">
						Save SSH Key
					</v-btn>
				</v-col>
			</v-row>
			</v-container>
			</v-card-text>
		</v-card>
	</v-dialog>
	<v-dialog v-model="new_process.show" :persistent="true" width="800" v-if="role == 'administrator' || role == 'owner'">
		<v-card>
			<v-toolbar flat color="primary">
			<v-btn icon @click="new_process.show = false"><v-icon>mdi-close</v-icon></v-btn>
			<v-toolbar-title>New Process</v-toolbar-title>
			<v-spacer></v-spacer>
			</v-toolbar>
			<v-card-text>
				<v-row>
					<v-col cols="12"><v-text-field label="Name" v-model="new_process.name" variant="underlined"></v-text-field></v-col>
					<v-col cols="12" sm="3"><v-text-field label="Time Estimate" hint="Example: 15 minutes" persistent-hint v-model="new_process.time_estimate" variant="underlined"></v-text-field></v-col>
					<v-col cols="12" sm="3"><v-select :items='[{"title":"As needed","value":"as-needed"},{"title":"Daily","value":"1-daily"},{"title":"Weekly","value":"2-weekly"},{"title":"Monthly","value":"3-monthly"},{"title":"Yearly","value":"4-yearly"}]' label="Repeat" v-model="new_process.repeat_interval" variant="underlined"></v-select></v-col>
					<v-col cols="12" sm="3"><v-text-field label="Repeat Quantity" hint="Example: 2 or 3 times" persistent-hint v-model="new_process.repeat_quantity" variant="underlined"></v-text-field></v-col>
					<v-col cols="12" sm="3"><v-autocomplete :items="process_roles" item-title="name" item-value="role_id" label="Role" hide-details v-model="new_process.roles" variant="underlined"></v-autocomplete></v-col>
					<v-col cols="12"><v-textarea label="Description" persistent-hint hint="Steps to accomplish this process. Markdown enabled." auto-grow v-model="new_process.description" variant="underlined"></v-textarea></v-col>
				</v-row>
			</v-card-text>
			<v-card-actions class="d-flex justify-end"><v-btn color="primary" @click="addNewProcess()">Add New Process</v-btn></v-card-actions>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_edit_process.show" :persistent="true" width="800" v-if="role == 'administrator'">
			<v-card>
				<v-toolbar flat color="surface">
				<v-btn icon @click="dialog_edit_process.show = false">
					<v-icon>mdi-close</v-icon>
				</v-btn>
				<v-toolbar-title>Edit Process</v-toolbar-title>
				<v-spacer></v-spacer>
				</v-toolbar>
				<v-card-text>
				<v-container>
					<v-row>
					<v-col cols="12">
						<v-text-field label="Name" v-model="dialog_edit_process.process.name" variant="underlined"></v-text-field>
					</v-col>
					<v-col cols="12" sm="3">
						<v-text-field label="Time Estimate" hint="Example: 15 minutes" persistent-hint v-model="dialog_edit_process.process.time_estimate" variant="underlined"></v-text-field>
					</v-col>
					<v-col cols="12" sm="3">
						<v-select :items='[{"title":"As needed","value":"as-needed"},{"title":"Daily","value":"1-daily"},{"title":"Weekly","value":"2-weekly"},{"title":"Monthly","value":"3-monthly"},{"title":"Yearly","value":"4-yearly"}]' label="Repeat" v-model="dialog_edit_process.process.repeat_interval" variant="underlined"></v-select>
					</v-col>
					<v-col cols="12" sm="3">
						<v-text-field label="Repeat Quantity" hint="Example: 2 or 3 times" persistent-hint v-model="dialog_edit_process.process.repeat_quantity" variant="underlined"></v-text-field>
					</v-col>
					<v-col cols="12" sm="3">
						<v-autocomplete :items="process_roles" item-title="name" item-value="role_id" label="Role" hide-details v-model="dialog_edit_process.process.roles" variant="underlined"></v-autocomplete>
					</v-col>
					<v-col cols="12">
						<v-textarea label="Description" persistent-hint hint="Steps to accomplish this process. Markdown enabled." auto-grow v-model="dialog_edit_process.process.description" variant="underlined"></v-textarea>
					</v-col>
					</v-row>
				</v-container>
				</v-card-text>
				<v-card-actions class="d-flex justify-end">
				<v-btn color="primary" @click="saveProcess()">
					Save Process
				</v-btn>
				</v-card-actions>
			</v-card>
			</v-dialog>
		<v-dialog v-model="dialog_handbook.show" v-if="role == 'administrator'" scrollable persistent>
			<v-card tile>
			<v-toolbar flat>
				<v-btn icon @click.native="dialog_handbook.show = false">
					<v-icon>mdi-close</v-icon>
				</v-btn>
				<v-toolbar-title>{{ dialog_handbook.process.name }} <v-chip color="primary" text-color="white" text v-show="dialog_handbook.process.roles != ''">{{ dialog_handbook.process.roles }}</v-chip></v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items>
					<v-btn variant="text" @click="editProcess()">Edit</v-btn>
				</v-toolbar-items>
			</v-toolbar>
			<v-card-text style="max-height: 100%;">
				<div class="text-caption my-3">
					<v-icon size="small" v-show="dialog_handbook.process.time_estimate != ''" style="padding:0px 5px">mdi-clock-outline</v-icon>{{ dialog_handbook.process.time_estimate }} 
					<v-icon size="small" v-show="dialog_handbook.process.repeat != '' && dialog_handbook.process.repeat != null" style="padding:0px 5px">mdi-calendar-repeat</v-icon>{{ dialog_handbook.process.repeat }} 
					<v-icon size="small" v-show="dialog_handbook.process.repeat_quantity != '' && dialog_handbook.process.repeat_quantity != null" style="padding:0px 5px">mdi-repeat</v-icon>{{ dialog_handbook.process.repeat_quantity }}
				</div>
				<span v-html="dialog_handbook.process.description"></span>
			</v-card-text>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_update_settings.show" max-width="500px">
		<v-card rounded="0">
			<v-toolbar flat color="primary">
				<v-btn icon="mdi-close" @click="dialog_update_settings.show = false"></v-btn>
				<v-toolbar-title>Save settings for {{ dialog_site.site.name }}</v-toolbar-title>
			</v-toolbar>
			<v-card-text>
				<v-row>
					<v-col>
						<v-switch 
							label="Automatic Updates" 
							v-model="dialog_update_settings.environment.updates_enabled" 
							:false-value="0" 
							:true-value="1" 
							color="primary" 
							inset
							hide-details
							class="mt-4">
						</v-switch>
					</v-col>
				</v-row>

				<v-select
					:items="dialog_update_settings.plugins"
					item-title="title"
					item-value="name"
					v-model="dialog_update_settings.environment.updates_exclude_plugins"
					label="Excluded Plugins"
					variant="underlined"
					multiple
					chips
					closable-chips
					persistent-hint
					class="mt-4"
				></v-select>

				<v-select
					:items="dialog_update_settings.themes"
					item-title="title"
					item-value="name"
					v-model="dialog_update_settings.environment.updates_exclude_themes"
					label="Excluded Themes"
					variant="underlined"
					multiple
					chips
					closable-chips
					persistent-hint
					class="mt-4"
				></v-select>

				<v-progress-linear indeterminate v-if="dialog_update_settings.loading" class="my-4"></v-progress-linear>

				<v-btn @click="saveUpdateSettings()" color="primary" class="mt-4">Save Update Settings</v-btn>
			</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_theme_and_plugin_checks.show" width="500">
        <v-card tile>
          <v-toolbar flat dark color="primary">
				<v-btn icon dark @click.native="dialog_theme_and_plugin_checks.show = false">
					<v-icon>mdi-close</v-icon>
				</v-btn>
				<v-toolbar-title>Theme & plugin checks for {{ dialog_theme_and_plugin_checks.site.name }}</v-toolbar-title>
            <v-spacer></v-spacer>
          </v-toolbar>
          <v-card-text>

				<p>Enables daily checks to verify a theme/plugin is a certain status (activate/inactive). Will email notify if a check fails.</p>

				<v-switch label="Theme & Plugin Checks" v-model="dialog_theme_and_plugin_checks.theme_and_plugin_checks" false-value="0" true-value="1"></v-switch>
				<v-data-table
					:items='[{ slug: "wordpress-seo", status: "active" },{ slug: "enhanced-e-commerce-for-woocommerce-store", status: "active"}]'
					hide-default-footer
					hide-default-header
					class="elevation-1"
					v-show="dialog_theme_and_plugin_checks.theme_and_plugin_checks == 1"
				>
				<template v-slot:body="{ items }">
				<tbody>
					<tr v-for="item in items">
						<td>
							<v-text-field v-model="item.slug" label="Slug" required></v-text-field>
						</td>
						<td class="text-right">
							<v-select
								:items='["active","inactive","active-network"]'
								box
								label="Status"
								:value="item.status"
							>
							</v-select>
						</td>
						<td class="justify-center layout px-0">
							<v-icon size="small" @click="deleteItem(item)">mdi-delete</v-icon>
						</td>
					</tr>
					<tr>
						<td colspan="100%" class="text-right">
							<v-btn @click="deleteItem(props.item)">
								Add new check
							</v-btn>
						</td>
					</tr>
				</tbody>
				</template>
			  </v-data-table>
				<v-progress-linear :indeterminate="true" v-if="dialog_theme_and_plugin_checks.loading"></v-progress-linear>
				<v-btn @click="savethemeAndPluginChecks()">Save Checks</v-btn>
          </v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_new_domain.show" scrollable width="500">
			<v-card>
				<v-toolbar color="primary">
				<v-btn icon="mdi-close" @click="dialog_new_domain.show = false"></v-btn>
				<v-toolbar-title>Add Domain</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card-text>
					<v-text-field variant="underlined" v-model="dialog_new_domain.domain.name" label="Domain Name" required class="mt-3"></v-text-field>
					<v-autocomplete variant="underlined" :items="accounts" item-title="name" item-value="account_id" v-model="dialog_new_domain.domain.account_id" label="Account" required v-if="role == 'administrator'"></v-autocomplete>
					<v-autocomplete variant="underlined" :items="sites" item-title="name" item-value="site_id" v-model="dialog_new_domain.domain.site_id" label="Website" required v-if="role != 'administrator'"></v-autocomplete>
					<v-switch
						v-model="dialog_new_domain.domain.create_dns_zone"
						label="Create DNS Zone"
						color="primary"
						inset
						persistent-hint
						hint="Automatically create and manage DNS records for this domain."
						class="mt-2"
					></v-switch>
					<v-alert variant="tonal" type="error" class="text-body-1 mb-3" v-for="error in dialog_new_domain.errors">
						{{ error }}
					</v-alert>
					<v-progress-linear indeterminate rounded height="6" class="mb-3" v-show="dialog_new_domain.loading"></v-progress-linear>
					<div class="d-flex justify-end">
						<v-btn color="primary" @click="addDomain()">
							Add domain
						</v-btn>
					</div>
				</v-card-text>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_new_provider.show" scrollable width="500">
			<v-card>
				<v-toolbar color="primary">
				<v-btn icon="mdi-close" @click="dialog_new_provider.show = false"></v-btn>
				<v-toolbar-title>Add Provider</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card-text>
					<v-text-field :model-value="dialog_new_provider.provider.name" @update:model-value="dialog_new_provider.provider.name = $event" label="Provider Name" required class="mt-3" variant="underlined"></v-text-field>
					<v-autocomplete :items="provider_options" v-model="dialog_new_provider.provider.provider" label="Provider" required variant="underlined"></v-autocomplete>
					Credentials
					<v-row no-gutters v-for="(item, index) in dialog_new_provider.provider.credentials" :key="index">
						<v-col cols="12" sm="5">
							<v-text-field hide-details :model-value="item.name" @update:model-value="item.name = $event" label="Name" required variant="underlined"></v-text-field>
						</v-col>
						<v-col cols="12" sm="6">
							<v-text-field hide-details :model-value="item.value" @update:model-value="item.value = $event" label="Value" required class="mx-2" variant="underlined"></v-text-field>
						</v-col>
						<v-col sm="1">
							<v-btn icon="mdi-delete" variant="text" @click="dialog_new_provider.provider.credentials.splice(index, 1)" class="mt-2"></v-btn>
						</v-col>
					</v-row>
					<v-btn variant="tonal" class="my-2" @click="dialog_new_provider.provider.credentials.push( {'name':'', 'value': ''} )" >Add Additional Credential</v-btn>
					<v-alert variant="tonal" type="error" v-for="error in dialog_new_provider.errors" :key="error">
						{{ error }}
					</v-alert>
					<v-progress-linear indeterminate rounded height="6" class="mb-3" v-show="dialog_new_provider.loading"></v-progress-linear>
					<div class="d-flex justify-end">
						<v-btn color="primary" @click="addProvider()">
							Add Provider
						</v-btn>
					</div>
				</v-card-text>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_edit_provider.show" scrollable width="500">
			<v-card>
				<v-toolbar color="primary">
				<v-btn icon="mdi-close" @click="dialog_edit_provider.show = false"></v-btn>
				<v-toolbar-title>Edit Provider</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card-text>
					<v-text-field :model-value="dialog_edit_provider.provider.name" @update:model-value="dialog_edit_provider.provider.name = $event" label="Provider Name" required class="mt-3" variant="underlined"></v-text-field>
					<v-autocomplete :items="provider_options" v-model="dialog_edit_provider.provider.provider" label="Provider" required variant="underlined"></v-autocomplete>
					Credentials
					<v-row no-gutters v-for="(item, index) in dialog_edit_provider.provider.credentials" :key="index">
						<v-col cols="12" sm="5">
							<v-text-field hide-details :model-value="item.name" @update:model-value="item.name = $event" label="Name" required variant="underlined"></v-text-field>
						</v-col>
						<v-col cols="12" sm="6">
							<v-text-field hide-details :model-value="item.value" @update:model-value="item.value = $event" label="Value" required class="mx-2" variant="underlined"></v-text-field>
						</v-col>
						<v-col sm="1">
							<v-btn icon="mdi-delete" @click="dialog_edit_provider.provider.credentials.splice(index, 1)" class="mt-2" variant="text"></v-btn>
						</v-col>
					</v-row>
					<v-btn variant="tonal" class="my-2 mr-2" @click="dialog_edit_provider.provider.credentials.push( {'name':'', 'value': ''} )" >Add Additional Credential</v-btn>
					<v-alert variant="tonal" type="error" v-for="error in dialog_edit_provider.errors" :key="error">
						{{ error }}
					</v-alert>
					<v-progress-linear indeterminate rounded height="6" class="mb-3" v-show="dialog_edit_provider.loading"></v-progress-linear>
					<div class="d-flex justify-end">
						<v-btn color="error" variant="text" @click="deleteProvider()">
							Delete Provider
						</v-btn>
						<v-btn color="primary" @click="updateProvider()">
							Update Provider
						</v-btn>
					</div>
				</v-card-text>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_configure_defaults.show" scrollable width="980">
		<v-card>
			<v-toolbar flat color="primary">
			<v-btn icon="mdi-close" dark @click.native="dialog_configure_defaults.show = false"></v-btn>
			<v-toolbar-title>Configure Defaults</v-toolbar-title>
			<v-spacer></v-spacer>
			</v-toolbar>
			<template v-if="dialog_configure_defaults.loading">
			<v-progress-linear :indeterminate="true"></v-progress-linear>
			</template>
			<v-card-text>
			<template v-if="dialog_account.show">
				<v-alert variant="tonal" type="info" class="text-body-1 my-4" size="small">When new sites are added to the account <strong>{{ dialog_account.records.account.name }}</strong> then the following default settings will be applied.</v-alert>
				<v-row wrap>
				<v-col cols="6" pr-2>
					<v-text-field variant="underlined" :model-value="dialog_account.records.account.defaults.email" @update:model-value="dialog_account.records.account.defaults.email = $event" label="Default Email" required></v-text-field>
				</v-col>
				<v-col cols="6" pl-2>
					<v-autocomplete variant="underlined" :items="timezones" label="Default Timezone" v-model="dialog_account.records.account.defaults.timezone"></v-autocomplete>
				</v-col>
				</v-row>
				<v-row wrap>
				<v-col>
					<v-autocomplete variant="underlined" label="Default Recipes" v-model="dialog_account.records.account.defaults.recipes" ref="default_recipes" :items="recipes" item-title="title" item-value="recipe_id" multiple chips closable-chips :menu-props="{ closeOnContentClick:true, openOnClick: false }"></v-autocomplete>
				</v-col>
				</v-row>
				<span class="body-2">Default Users</span>
				<v-data-table :items="dialog_account.records.account.defaults.users" hide-default-header hide-default-footer v-if="typeof dialog_account.records.account.defaults.users == 'object'">
				<template v-slot:body="{ items }">
					<tr v-for="(item, index) in items">
						<td class="px-1" style="border: 0px;">
							<v-text-field variant="underlined" :model-value="item.username" @update:model-value="item.username = $event" label="Username"></v-text-field>
						</td>
						<td class="px-1" style="border: 0px;">
							<v-text-field variant="underlined" :model-value="item.email" @update:model-value="item.email = $event" label="Email"></v-text-field>
						</td>
						<td class="px-1" style="border: 0px;">
							<v-text-field variant="underlined" :model-value="item.first_name" @update:model-value="item.first_name = $event" label="First Name"></v-text-field>
						</td>
						<td class="px-1" style="border: 0px;">
							<v-text-field variant="underlined" :model-value="item.last_name" @update:model-value="item.last_name = $event" label="Last Name"></v-text-field>
						</td>
						<td class="px-1" style="border: 0px;width:155px;">
							<v-select variant="underlined" :model-value="item.role" v-model="item.role" :items="roles" label="Role" item-title="name"></v-select>
						</td>
						<td class="px-1" style="border: 0px;width:50px;">
							<v-btn variant="text" icon="mdi-delete" density="compact" color="primary" @click="deleteUserValue( index )"></v-btn>
						</td>
					</tr>
				</template>
				<template v-slot:bottom>
					<div class="v-data-table-footer">
					<v-row style="border-top: 0px;">
						<v-col cols="12">
						<v-btn variant="tonal" size="small" @click="addDefaultsUser()">Add Additional User</v-btn>
						</v-col>
					</v-row>
					</div>
				</template>
				</v-data-table>
				<v-spacer class="my-5"></v-spacer>
				<v-row>
				<v-col cols="12">
					<v-text-field variant="underlined" :model-value="dialog_account.records.account.defaults.kinsta_emails" @update:model-value="dialog_account.records.account.defaults.kinsta_emails = $event" label="Kinsta Email Invite(s)" persistent-hint hint="Separated by a comma. Example: name@example.com, support@example.com. When Kinsta site is created from this panel, will share MyKinsta access with these email addresses."></v-text-field>
				</v-col>
				<v-col cols="12" text-right>
				<v-btn color="primary" dark @click="saveDefaults()">
					Save Changes
				</v-btn>
				</v-col>
				</v-row>
			</template>
			</v-card-text>
		</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_customer_modify_plan.show" max-width="700">
			<v-card tile>
				<v-toolbar elevation="0" color="primary">
					<v-btn icon @click="dialog_customer_modify_plan.show = false">
						<v-icon>mdi-close</v-icon>
					</v-btn>
					<v-toolbar-title>Edit plan for {{ dialog_customer_modify_plan.subscription.name }}</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card-text class="mt-4">
					<v-row>
						<v-col cols="6" px-1>
							<v-select
								v-show="dialog_customer_modify_plan.hosting_plans.map( plan => plan.name ).includes( dialog_customer_modify_plan.selected_plan )"
								v-model="dialog_customer_modify_plan.selected_plan"
								label="Plan Name"
								:items="dialog_customer_modify_plan.hosting_plans.map( plan => plan.name )"
								:model-value="dialog_customer_modify_plan.subscription.plan.name"
								variant="underlined"
							></v-select>
						</v-col>
						<v-col cols="6" px-1>
							<v-select
								v-show="dialog_customer_modify_plan.subscription.plan.interval != ''"
								v-model="dialog_customer_modify_plan.subscription.plan.interval"
								label="Plan Interval"
								:items="hosting_intervals"
								:model-value="dialog_customer_modify_plan.subscription.plan.interval"
								variant="underlined"
							></v-select>
						</v-col>
						<v-col cols="6" px-1>
							<v-switch
								v-if="typeof dialog_customer_modify_plan.subscription.plan.auto_pay != 'undefined'"
								v-model="dialog_customer_modify_plan.subscription.plan.auto_pay"
								false-value="false"
								true-value="true"
								label="Autopay"
							></v-switch>
						</v-col>
						<v-col cols="6" px-1>
							<v-text-field
								disabled
								:model-value="dialog_customer_modify_plan.subscription.plan.next_renewal"
								label="Next Renewal Date"
								prepend-icon="mdi-calendar"
								variant="underlined"
							></v-text-field>
						</v-col>
					</v-row>
					<v-row v-if="typeof dialog_customer_modify_plan.subscription.plan.name == 'string' && dialog_customer_modify_plan.subscription.plan.name == 'Custom'">
						<v-col cols="3" pa-1>
							<v-text-field
								label="Storage (GBs)"
								:model-value="dialog_customer_modify_plan.subscription.plan.limits.storage"
								@update:model-value="dialog_customer_modify_plan.subscription.plan.limits.storage = $event"
								variant="underlined"
							></v-text-field>
						</v-col>
						<v-col cols="3" pa-1>
							<v-text-field
								label="Visits"
								:model-value="dialog_customer_modify_plan.subscription.plan.limits.visits"
								@update:model-value="dialog_customer_modify_plan.subscription.plan.limits.visits = $event"
								variant="underlined"
							></v-text-field>
						</v-col>
						<v-col cols="3" pa-1>
							<v-text-field
								label="Sites"
								:model-value="dialog_customer_modify_plan.subscription.plan.limits.sites"
								@update:model-value="dialog_customer_modify_plan.subscription.plan.limits.sites = $event"
								variant="underlined"
							></v-text-field>
						</v-col>
						<v-col cols="3" pa-1>
							<v-text-field
								label="Price"
								:model-value="dialog_customer_modify_plan.subscription.plan.price"
								@update:model-value="dialog_customer_modify_plan.subscription.plan.price = $event"
								variant="underlined"
							></v-text-field>
						</v-col>
					</v-row>
					<v-row v-else-if="Object.keys( dialog_customer_modify_plan.subscription.plan.limits ).length > 0">
						<v-col cols="3" pa-1>
							<v-text-field label="Storage (GBs)" :model-value="dialog_customer_modify_plan.subscription.plan.limits.storage" disabled></v-text-field>
						</v-col>
						<v-col cols="3" pa-1>
							<v-text-field label="Visits" :model-value="dialog_customer_modify_plan.subscription.plan.limits.visits" disabled></v-text-field>
						</v-col>
						<v-col cols="3" pa-1>
							<v-text-field label="Sites" :model-value="dialog_customer_modify_plan.subscription.plan.limits.sites" disabled></v-text-field>
						</v-col>
						<v-col cols="3" pa-1>
							<v-text-field label="Price" :model-value="dialog_customer_modify_plan.subscription.plan.price" disabled></v-text-field>
						</v-col>
					</v-row>
					<v-data-table
						v-show="dialog_customer_modify_plan.subscription.plan.addons.length > 0"
						:headers='[{"title":"Name","value":"name"},{"title":"Quantity","value":"quantity"},{"title":"Price","value":"price"},{"title":"Total","value":"total"}]'
						:items="dialog_customer_modify_plan.subscription.plan.addons"
						:items-per-page-options="[50,100,250,{title:'All',value:-1}]"
					>
						<template v-slot:item.price="{ item }">
							${{ item.price }}
						</template>
						<template v-slot:item.total="{ item }">
							${{ ( item.price * item.quantity ).toFixed(2) }}
						</template>
					</v-data-table>
					<v-row>
						<v-col cols="12" class="text-right">
							<v-btn color="red" variant="flat" @click="cancelPlan()">
								Cancel Plan
							</v-btn>
							<v-btn color="primary" variant="flat" @click="requestPlanChanges()">
								Request Changes
							</v-btn>
						</v-col>
					</v-row>
				</v-card-text>
			</v-card>
		</v-dialog>
		<v-dialog v-model="dialog_modify_plan.show" max-width="700">
			<v-card>
				<v-toolbar flat color="primary">
					<v-btn icon="mdi-close" @click="dialog_modify_plan.show = false"></v-btn>
					<v-toolbar-title>Edit plan for {{ dialog_account.records.account.name }}</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card-text class="mt-4">
					<v-row dense>
						<v-col cols="12" md="6">
							<v-select
								@update:model-value="loadHostingPlan($event)"
								v-model="dialog_modify_plan.selected_plan"
								label="Plan Name"
								:items="(dialog_modify_plan.hosting_plans || []).map( plan => plan.name )"
								variant="underlined"
							></v-select>
						</v-col>
						<v-col cols="12" md="6">
							<v-select
								@update:model-value="calculateHostingPlan()"
								v-model="dialog_modify_plan.plan.interval"
								label="Plan Interval"
								:items="hosting_intervals || []"
								variant="underlined"
							></v-select>
						</v-col>
						<v-col cols="12" md="6">
							<v-select v-if="typeof dialog_account.records.users == 'object'" label="Billing User" :items="dialog_account.records.users || []" :item-title="item => `${item.name} - ${item.email}`" item-value="user_id" v-model="dialog_modify_plan.plan.billing_user_id" variant="underlined"></v-select>
						</v-col>
						<v-col cols="12" md="6">
							<v-menu v-model="dialog_modify_plan.date_selector" :close-on-content-click="false" location="bottom">
								<template v-slot:activator="{ props }">
								<v-text-field
									v-model="dialog_modify_plan.plan.next_renewal"
									label="Next Renewal Date"
									append-inner-icon="mdi-calendar"
									readonly
									v-bind="props"
									variant="underlined"
								></v-text-field>
								</template>
								<v-date-picker @update:model-value="keepTimestamp" :model-value="dialog_modify_plan.plan.next_renewal ? new Date(dialog_modify_plan.plan.next_renewal.split(' ')[0]) : null"></v-date-picker>
							</v-menu>
						</v-col>
					</v-row>
					<v-row dense align="center">
						<v-col cols="12" md="4"><v-switch v-model="dialog_modify_plan.plan.auto_pay" false-value="false" true-value="true" label="Autopay" color="primary" inset hide-details></v-switch></v-col>
						<v-col cols="12" md="4"><v-switch v-model="dialog_modify_plan.plan.auto_switch" false-value="false" true-value="true" label="Auto switch plan" color="primary" inset hide-details></v-switch></v-col>
                        <v-col cols="12" md="4" class="pt-2">
                             <v-radio-group v-model="dialog_modify_plan.plan.billing_mode" inline hide-details density="compact">
                                <v-radio label="Standard" value="standard"></v-radio>
                                <v-radio label="Per Site" value="per_site"></v-radio>
                            </v-radio-group>
                        </v-col>
					</v-row>
                    
                    <v-row v-if="dialog_modify_plan.plan.billing_mode === 'per_site'" dense>
						<v-col cols="12" sm="3"><v-text-field label="Storage" value="Unlimited" disabled variant="underlined"></v-text-field></v-col>
						<v-col cols="12" sm="3"><v-text-field label="Visits" value="Unlimited" disabled variant="underlined"></v-text-field></v-col>
						<v-col cols="12" sm="3"><v-text-field label="Sites" :model-value="(dialog_account.records.account.plan.usage.sites || 0) + ' Active'" disabled variant="underlined" hint="Billed based on count" persistent-hint></v-text-field></v-col>
						<v-col cols="12" sm="3"><v-text-field label="Price Per Site" v-model="dialog_modify_plan.plan.price" variant="underlined" prefix="$"></v-text-field></v-col>
					</v-row>

					<v-row v-else-if="typeof dialog_modify_plan.plan.name == 'string' && dialog_modify_plan.plan.name == 'Custom'" dense>
						<v-col cols="12" sm="3"><v-text-field label="Storage (GBs)" :model-value="dialog_modify_plan.plan.limits.storage" @update:model-value="dialog_modify_plan.plan.limits.storage = $event" variant="underlined"></v-text-field></v-col>
						<v-col cols="12" sm="3"><v-text-field label="Visits" :model-value="dialog_modify_plan.plan.limits.visits" @update:model-value="dialog_modify_plan.plan.limits.visits = $event" variant="underlined"></v-text-field></v-col>
						<v-col cols="12" sm="3"><v-text-field label="Sites" :model-value="dialog_modify_plan.plan.limits.sites" @update:model-value="dialog_modify_plan.plan.limits.sites = $event" variant="underlined"></v-text-field></v-col>
						<v-col cols="12" sm="3"><v-text-field label="Price" :model-value="dialog_modify_plan.plan.price" @update:model-value="dialog_modify_plan.plan.price = $event" variant="underlined"></v-text-field></v-col>
					</v-row>

					<v-row v-else dense>
						<v-col cols="12" sm="3"><v-text-field label="Storage (GBs)" :model-value="dialog_modify_plan.plan.limits.storage" disabled variant="underlined"></v-text-field></v-col>
						<v-col cols="12" sm="3"><v-text-field label="Visits" :model-value="dialog_modify_plan.plan.limits.visits" disabled variant="underlined"></v-text-field></v-col>
						<v-col cols="12" sm="3"><v-text-field label="Sites" :model-value="dialog_modify_plan.plan.limits.sites" disabled variant="underlined"></v-text-field></v-col>
						<v-col cols="12" sm="3"><v-text-field label="Price" :model-value="dialog_modify_plan.plan.price" disabled variant="underlined"></v-text-field></v-col>
					</v-row>

					<v-row dense>
						<v-col>
							<h3 v-show="typeof dialog_modify_plan.plan.addons == 'object' && dialog_modify_plan.plan.addons">Addons</h3>
						</v-col>
					</v-row>
					<v-row density="compact" v-for="(addon, index) in dialog_modify_plan.plan.addons" :key="`addon-${index}`">
						<v-col cols="7">
							<v-textarea auto-grow rows="1" label="Name" :model-value="addon.name" @update:model-value="addon.name = $event" hide-details variant="underlined" :disabled="addon.required"></v-textarea>
						</v-col>
						<v-col cols="2">
							<v-text-field label="Quantity" :model-value="addon.quantity" @update:model-value="addon.quantity = $event" hide-details variant="underlined" :disabled="addon.required"></v-text-field>
						</v-col>
						<v-col cols="2">
							<v-text-field label="Price" :model-value="addon.price" @update:model-value="addon.price = $event" hide-details variant="underlined" :disabled="addon.required"></v-text-field>
						</v-col>
						<v-col cols="1" align-self="center">
							<v-btn size="small" variant="text" icon="mdi-delete" @click="removePlanItem('addons', index)" v-show="! addon.required"></v-btn>
						</v-col>
					</v-row>
					<v-row class="mb-1">
						<v-col>
							<v-btn size="small" variant="tonal" @click="addPlanItem('addons')">Add Addon</v-btn>
						</v-col>
					</v-row>
					<v-row dense>
						<v-col>
							<h3 v-show="typeof dialog_modify_plan.plan.credits == 'object' && dialog_modify_plan.plan.credits">Credits</h3>
						</v-col>
					</v-row>
					<v-row density="compact" v-for="(item, index) in dialog_modify_plan.plan.credits" :key="`credit-${index}`">
						<v-col cols="7">
							<v-textarea auto-grow rows="1" label="Name" :model-value="item.name" @update:model-value="item.name = $event" hide-details variant="underlined"></v-textarea>
						</v-col>
						<v-col cols="2">
							<v-text-field label="Quantity" :model-value="item.quantity" @update:model-value="item.quantity = $event" hide-details variant="underlined"></v-text-field>
						</v-col>
						<v-col cols="2">
							<v-text-field label="Price" :model-value="item.price" @update:model-value="item.price = $event" hide-details variant="underlined"></v-text-field>
						</v-col>
						<v-col cols="1" align-self="center">
							<v-btn size="small" variant="text" icon="mdi-delete" @click="removePlanItem('credits', index)"></v-btn>
						</v-col>
					</v-row>
					<v-row class="mb-1">
						<v-col>
							<v-btn size="small" variant="tonal" @click="addPlanItem('credits')">Add Credit</v-btn>
						</v-col>
					</v-row>
					<v-row dense>
						<v-col>
							<h3 v-show="typeof dialog_modify_plan.plan.charges == 'object' && dialog_modify_plan.plan.charges">Charges</h3>
						</v-col>
					</v-row>
					<v-row density="compact" v-for="(item, index) in dialog_modify_plan.plan.charges" :key="`charge-${index}`">
						<v-col cols="7">
							<v-textarea auto-grow rows="1" label="Name" :model-value="item.name" @update:model-value="item.name = $event" hide-details variant="underlined"></v-textarea>
						</v-col>
						<v-col cols="2">
							<v-text-field label="Quantity" :model-value="item.quantity" @update:model-value="item.quantity = $event" hide-details variant="underlined"></v-text-field>
						</v-col>
						<v-col cols="2">
							<v-text-field label="Price" :model-value="item.price" @update:model-value="item.price = $event" hide-details variant="underlined"></v-text-field>
						</v-col>
						<v-col cols="1" align-self="center">
							<v-btn size="small" variant="text" icon="mdi-delete" @click="removePlanItem('charges', index)"></v-btn>
						</v-col>
					</v-row>
					<v-row class="mb-1">
						<v-col>
							<v-btn size="small" variant="tonal" @click="addPlanItem('charges')">Add Charge</v-btn>
						</v-col>
					</v-row>
					<v-row>
						<v-col cols="12">
							<v-text-field label="Additional Emails" persistent-hint hint="Separated by a comma. Example: name@example.com, support@example.com" :model-value="dialog_modify_plan.plan.additional_emails" @update:model-value="dialog_modify_plan.plan.additional_emails = $event" variant="underlined"></v-text-field>
						</v-col>
					</v-row>
					<v-row>
						<v-col cols="12">
							<v-btn color="primary" @click="updatePlan()">
								Save Changes
							</v-btn>
						</v-col>
					</v-row>
				</v-card-text>
			</v-card>
		</v-dialog>
				<v-dialog v-if="role == 'administrator'" v-model="dialog_log_history.show" scrollable>
				<v-card tile>
					<v-toolbar flat color="primary">
						<v-btn variant="text" @click.native="dialog_log_history.show = false" icon="mdi-close"></v-btn>
						<v-toolbar-title>Log History</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-data-table
						:headers="header_timeline"
						:items="dialog_log_history.logs"
						:footer-props="{ itemsPerPageOptions: [50,100,250,{'text':'All','value':-1}] }"
						:loading="dialog_log_history.loading"
						loading-text="Loading... Please wait"
						class="timeline"
					>
						<template v-slot:body="{ items }">
						<tr v-for="item in items">
						<td class="justify-center pt-3 pr-0 text-center shrink" style="vertical-align: top;">
							<v-tooltip location="bottom">
							<template v-slot:activator="{ props }">
								<v-icon color="primary" v-bind="props" v-show="item.name">mdi-note</v-icon>
							</template>
							<span>{{ item.name }}</span>
							</v-tooltip>
							<v-icon color="primary" v-show="! item.name">mdi-checkbox-marked-circle</v-icon>
						</td>
						<td class="justify-center py-4" style="vertical-align: top;">
							<div v-html="item.description" v-show="item.description"></div>
						</td>
						<td class="justify-center pt-2" style="vertical-align:top; width:180px;">
							<v-row align="center" no-gutters>
								<v-col cols="auto" class="pr-2">
									<v-img :src="item.author_avatar" width="34" class="rounded"></v-img>
								</v-col>
								<v-col>
									<div class="text-no-wrap">{{ item.author }}</div>
								</v-col>
							</v-row>
						</td>
						<td class="justify-center pt-3" style="vertical-align: top;">{{ pretty_timestamp_epoch( item.created_at ) }}</td>
						<td class="justify-center pt-1 pr-0" style="vertical-align:top;width:77px;" v-if="role == 'administrator'">
							<v-menu :nudge-width="200" open-on-hover bottom offset-y>
							<template v-slot:activator="{ props }">
								<v-icon size="small" density="compact" v-bind="props">mdi-information</v-icon>
							</template>
							<v-card>
								<v-card-text>
								<div v-for="site in item.websites">
									<a :href="`${configurations.path}sites/${site.site_id}`" @click.prevent="goToPath( `/sites/${site.site_id}` )">{{ site.name }}</a>
								</div>
								</v-card-text>
							</v-card>
							</v-menu>
							<v-btn variant="text" density="compact" icon="mdi-pencil" @click="dialog_log_history.show = false; editLogEntry(item.websites, item.process_log_id)"></v-btn>
						</td>
						</tr>
						</template>
					</v-data-table>
					</v-card-text>
				</v-dialog>
				<v-dialog v-if="role == 'administrator' || role == 'owner'" v-model="dialog_new_log_entry.show" scrollable persistent width="500">
				<v-card rounded="0">
				<v-toolbar elevation="0" color="primary">
					<v-btn icon="mdi-close" @click="dialog_new_log_entry.show = false"></v-btn>
					<v-toolbar-title>Add a new log entry <span v-if="dialog_new_log_entry.site_name">for {{ dialog_new_log_entry.site_name }}</span></v-toolbar-title>
				</v-toolbar>
				<v-card-text>
					<v-container>
					<v-autocomplete
						v-model="dialog_new_log_entry.process"
						:items="processes"
						item-title="name"
						item-value="process_id"
						v-show="role == 'administrator'"
						variant="underlined"
					>
						<template v-slot:item="{ props, item }">
						<v-list-item v-bind="props" :title="null" :subtitle="null">
							<template v-if="typeof item.raw !== 'object'">
							<div v-text="item.raw"></div>
							</template>
							<template v-else>
							<div>
								<v-list-item-title v-html="item.raw.name"></v-list-item-title>
								<v-list-item-subtitle v-html="item.raw.repeat_interval + ' - ' + item.raw.roles"></v-list-item-subtitle>
							</div>
							</template>
						</v-list-item>
						</template>
					</v-autocomplete>
					<v-autocomplete
						v-model="dialog_new_log_entry.sites"
						:items="sites"
						item-title="name"
						return-object
						chips
						closable-chips
						multiple
						variant="underlined"
					>
					</v-autocomplete>
					<v-textarea variant="underlined" label="Description" auto-grow v-model="dialog_new_log_entry.description"></v-textarea>
					<v-col cols="12" class="text-right pa-0">
						<v-btn color="primary" style="margin:0px;" @click="newLogEntry()"> Add Log Entry </v-btn>
					</v-col>
					</v-container>
				</v-card-text>
				</v-card>
				</v-dialog>
				<v-dialog v-if="role == 'administrator'" v-model="dialog_edit_log_entry.show" scrollable width="500">
				<v-card rounded="0">
				<v-toolbar color="primary" elevation="0">
					<v-btn icon="mdi-close" @click="dialog_edit_log_entry.show = false"></v-btn>
					<v-toolbar-title>Edit log entry <span v-if="dialog_edit_log_entry.site_name">for {{ dialog_edit_log_entry.site_name }}</span></v-toolbar-title>
				</v-toolbar>
				<v-card-text>
					<v-container>
					<v-text-field variant="underlined" v-model="dialog_edit_log_entry.log.created_at_raw" label="Date"></v-text-field>
					<v-autocomplete
						variant="underlined"
						v-model="dialog_edit_log_entry.log.process_id"
						:items="processes"
						item-title="name"
						item-value="process_id"
					>
						<template v-slot:item="{ props, item }">
						<v-list-item v-bind="props" :title="null" :subtitle="null">
							<template v-if="typeof item.raw !== 'object'">
							<div v-text="item.raw"></div>
							</template>
							<template v-else>
							<div>
								<v-list-item-title v-html="item.raw.name"></v-list-item-title>
								<v-list-item-subtitle v-html="item.raw.repeat_interval + ' - ' + item.raw.roles"></v-list-item-subtitle>
							</div>
							</template>
						</v-list-item>
						</template>
					</v-autocomplete>
					<v-autocomplete
						v-model="dialog_edit_log_entry.log.websites"
						variant="underlined"
						:items="sites"
						item-title="name"
						return-object
						chips
						closable-chips
						multiple
					>
					</v-autocomplete>
					<v-textarea variant="underlined" label="Description" auto-grow v-model="dialog_edit_log_entry.log.description_raw"></v-textarea>
					<v-col cols="12" class="text-right pa-0">
						<v-btn color="primary" style="margin:0px;" @click="updateLogEntry()"> Save Log Entry </v-btn>
					</v-col>
					</v-container>
				</v-card-text>
				</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_edit_script.show" scrollable max-width="750">
				<v-card rounded="0">
					<v-toolbar color="primary">
						<v-btn icon="mdi-close" @click="dialog_edit_script.show = false"></v-btn>
						<v-toolbar-title>Edit script</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-menu v-model="script.menu_time" :close-on-content-click="false" location="bottom">
							<template v-slot:activator="{ props }">
								<v-text-field 
									v-model="dialog_edit_script.script.run_at_time" 
									label="Time" 
									prepend-icon="mdi-clock-time-four-outline" 
									readonly 
									v-bind="props" 
									variant="underlined">
								</v-text-field>
							</template>
							<v-time-picker 
								v-if="script.menu_time" 
								v-model="dialog_edit_script.script.run_at_time" 
								@click:minute="script.menu_time = false"
								width="290">
							</v-time-picker>
						</v-menu>
						<v-menu v-model="script.menu_date" :close-on-content-click="false" location="bottom">
						<template v-slot:activator="{ props }">
							<v-text-field 
								v-model="dialog_edit_script.script.run_at_date" 
								label="Date" 
								prepend-icon="mdi-calendar" 
								readonly 
								v-bind="props" 
								variant="underlined">
							</v-text-field>
						</template>
						<v-date-picker 
							v-model="dialog_edit_script.script.run_at_date" 
							@update:model-value="script.menu_date = false" 
							hide-header 
							scrollable 
							:min="new Date().toISOString().substr(0, 10)">
						</v-date-picker>
					</v-menu>
						<v-textarea 
							label="Code" 
							auto-grow 
							:model-value="dialog_edit_script.script.code" 
							@update:model-value="dialog_edit_script.script.code = $event" 
							variant="underlined">
						</v-textarea>
						<div class="d-flex justify-end">
							<v-btn 
								variant="outlined" 
								color="error" 
								@click="deleteScript(dialog_edit_script.script.script_id)" 
								class="mr-2">
								Delete
							</v-btn>
							<v-btn color="primary" @click="updateScript()">
								Update Script
							</v-btn>
						</div>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_mailgun.show" scrollable fullscreen>
					<v-card rounded="0">
						<v-toolbar color="primary" class="shrink">
							<v-btn icon="mdi-close" @click="dialog_mailgun.show = false"></v-btn>
							<v-toolbar-title>Mailgun Logs for {{ dialog_mailgun.site.name }}
								<span v-if="dialog_mailgun.response.items.length" class="text-caption ml-2">
									({{ dialog_mailgun.response.items.length }})
								</span>
							</v-toolbar-title>
							<v-spacer></v-spacer>
							<v-btn 
								class="mr-4" 
								v-if="dialog_mailgun.response.paging && dialog_mailgun.response.paging.next" 
								variant="outlined" 
								@click="loadMoreMailgunLogs" 
								:loading="dialog_mailgun.loadingMore"
							>
								Load More
							</v-btn>
							<v-progress-linear
								:active="dialog_mailgun.loadingMore"
								indeterminate
								absolute
								bottom
								color="white"
							></v-progress-linear>
						</v-toolbar>
						
						<v-card-text>
							<v-container>
								<v-data-table
									:headers='[
										{"title":"Timestamp","key":"timestamp","sortable":false, "width": "194px"},
										{"title":"Event","key":"event","sortable":false, "width": "94px"},
										{"title":"From","key":"from","sortable":false},
										{"title":"To","key":"to","sortable":false},
										{"title":"Subject","key":"subject","sortable":false}
									]'
									:items="dialog_mailgun.response.items"
									:items-per-page="-1"
									:loading="dialog_mailgun.loading"
									hide-default-footer
								>
									<template v-slot:item="{ item }">
										<tr :key="item.id || item.timestamp" @click="viewMailgunEventDetails(item)" style="cursor: pointer" class="v-data-table__tr">
											<td class="text-caption">{{ pretty_timestamp_epoch(item.timestamp) }}</td>
											<td>
												<v-chip size="x-small" label :color="item.event === 'failed' ? 'error' : (item.event === 'delivered' ? 'success' : 'primary')">
													{{ item.event }}
												</v-chip>
											</td>
											<td class="text-caption">
												<div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
													{{ item.message?.headers?.from || item.envelope?.sender || '-' }}
												</div>
											</td>
											<td class="text-caption">
												<div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
													{{ item.message?.headers?.to || item.envelope?.targets || '-' }}
												</div>
											</td>
											<td class="text-caption">
												<div style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
													{{ item.message?.headers?.subject || '-' }}
												</div>
											</td>
										</tr>
									</template>

									<template v-slot:loading>
										<v-skeleton-loader type="table-row@5"></v-skeleton-loader>
									</template>

									<template v-slot:no-data>
										<div class="pa-4 text-center">No Mailgun logs available.</div>
									</template>
								</v-data-table>
								
								<div v-if="dialog_mailgun.loadingMore" class="text-center pa-4">
									<v-progress-circular indeterminate color="primary" size="24"></v-progress-circular>
								</div>

							</v-container>
						</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_mailgun_details.show" max-width="800px" scrollable>
					<v-card>
						<v-toolbar color="primary" flat>
							<v-btn icon="mdi-close" @click="dialog_mailgun_details.show = false"></v-btn>
							<v-toolbar-title>Event Details</v-toolbar-title>
						</v-toolbar>
						<v-card-text class="pa-0">
							<v-container>
								<v-row>
									<v-col cols="12" md="6">
										<v-list density="compact" class="pa-0">
											<v-list-subheader class="px-4">General</v-list-subheader>
											<v-list-item>
												<v-list-item-title>Timestamp</v-list-item-title>
												<v-list-item-subtitle>{{ pretty_timestamp_epoch(dialog_mailgun_details.item.timestamp) }}</v-list-item-subtitle>
											</v-list-item>
											<v-list-item>
												<v-list-item-title>Event</v-list-item-title>
												<v-list-item-subtitle>
													<v-chip size="x-small" label :color="dialog_mailgun_details.item.event === 'failed' ? 'error' : (dialog_mailgun_details.item.event === 'delivered' ? 'success' : 'primary')">
														{{ dialog_mailgun_details.item.event }}
													</v-chip>
												</v-list-item-subtitle>
											</v-list-item>
											<v-list-item>
												<v-list-item-title>ID</v-list-item-title>
												<v-list-item-subtitle class="text-caption">{{ dialog_mailgun_details.item.id }}</v-list-item-subtitle>
											</v-list-item>
											<v-list-item v-if="dialog_mailgun_details.item['log-level']">
												<v-list-item-title>Log Level</v-list-item-title>
												<v-list-item-subtitle>{{ dialog_mailgun_details.item['log-level'] }}</v-list-item-subtitle>
											</v-list-item>
										</v-list>
									</v-col>
									<v-col cols="12" md="6">
										<v-list density="compact" class="pa-0">
											<v-list-subheader class="px-4">Message</v-list-subheader>
											<v-list-item>
												<v-list-item-title>Subject</v-list-item-title>
												<v-list-item-subtitle style="white-space: normal; line-height: 1.2;">{{ dialog_mailgun_details.item.message?.headers?.subject || '-' }}</v-list-item-subtitle>
											</v-list-item>
											<v-list-item>
												<v-list-item-title>From</v-list-item-title>
												<v-list-item-subtitle>{{ dialog_mailgun_details.item.message?.headers?.from || dialog_mailgun_details.item.envelope?.sender || '-' }}</v-list-item-subtitle>
											</v-list-item>
											<v-list-item>
												<v-list-item-title>To</v-list-item-title>
												<v-list-item-subtitle>{{ dialog_mailgun_details.item.message?.headers?.to || dialog_mailgun_details.item.envelope?.targets || '-' }}</v-list-item-subtitle>
											</v-list-item>
											<v-list-item>
												<v-list-item-title>Size</v-list-item-title>
												<v-list-item-subtitle>{{ formatSize(dialog_mailgun_details.item.message?.size) }}</v-list-item-subtitle>
											</v-list-item>
										</v-list>
									</v-col>
								</v-row>

								<v-divider class="my-2"></v-divider>

								<div v-if="dialog_mailgun_details.item['delivery-status']" class="mb-3">
									<v-list-subheader>Delivery Status</v-list-subheader>
									<v-alert
										:type="dialog_mailgun_details.item['delivery-status'].code >= 400 ? 'error' : 'success'"
										variant="tonal"
										density="compact"
										class="mb-2 mx-4"
									>
										<strong>{{ dialog_mailgun_details.item['delivery-status'].code }}</strong> {{ dialog_mailgun_details.item['delivery-status'].message }}
										<div v-if="dialog_mailgun_details.item['delivery-status'].description" class="text-caption">{{ dialog_mailgun_details.item['delivery-status'].description }}</div>
									</v-alert>
									<v-row dense class="px-4">
										<v-col cols="6" md="3">
											<div class="text-caption text-medium-emphasis">MX Host</div>
											<div class="text-body-2">{{ dialog_mailgun_details.item['delivery-status']['mx-host'] || '-' }}</div>
										</v-col>
										<v-col cols="6" md="3">
											<div class="text-caption text-medium-emphasis">IP</div>
											<div class="text-body-2">{{ dialog_mailgun_details.item.envelope?.['sending-ip'] || '-' }}</div>
										</v-col>
										<v-col cols="6" md="3">
											<div class="text-caption text-medium-emphasis">TLS</div>
											<div class="text-body-2">{{ dialog_mailgun_details.item['delivery-status'].tls ? 'Verified' : 'No' }}</div>
										</v-col>
										<v-col cols="6" md="3">
											<div class="text-caption text-medium-emphasis">Attempts</div>
											<div class="text-body-2">{{ dialog_mailgun_details.item['delivery-status']['attempt-no'] || 1 }}</div>
										</v-col>
									</v-row>
								</div>
								
								<v-divider class="my-3"></v-divider>
								
								<h3 class="text-subtitle-1 mb-2 px-4">Raw Data</h3>
								<pre class="mx-4" style="white-space: pre-wrap; background: rgb(var(--v-theme-surface)); padding: 10px; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 4px; overflow: auto; max-height: 400px; font-size: 12px;">{{ JSON.stringify(dialog_mailgun_details.item, null, 2) }}</pre>
							</v-container>
						</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_backup_configurations.show" width="500">
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_backup_configurations.show = false">
							<v-icon>mdi-close</v-icon>
						</v-btn>
						<v-toolbar-title>Backup configurations</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-switch label="Active" v-model="dialog_backup_configurations.settings.active"></v-switch>
						<v-select label="Schedule" v-model="dialog_backup_configurations.settings.interval" :items="[{ title: 'Weekly', value: 'weekly' },{ title: 'Daily', value: 'daily' },{ title: 'Every 12 hours', value: '12-hours' },{ title: 'Every 6 hours', value: '6-hours' },{ title: 'Every hour', value: '1-hour' }]"></v-select>
						<v-select label="Mode" v-model="dialog_backup_configurations.settings.mode" :items="[{ title: 'Local copy', value: 'local' },{ title: 'Direct', value: 'direct' }]"></v-select>
						<v-btn @click="saveBackupConfigurations()">
							Update Configurations
						</v-btn>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_backup_snapshot.show" width="500">
				<v-card tile>
					<v-toolbar flat dark color="primary">
						<v-btn icon dark @click.native="dialog_backup_snapshot.show = false">
							<v-icon>mdi-close</v-icon>
						</v-btn>
						<v-toolbar-title>Download Snapshot {{ dialog_backup_snapshot.site.name }} </v-toolbar-title>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-text-field name="Email" v-model="dialog_backup_snapshot.email"></v-text-field>
					
							<v-switch v-model="dialog_backup_snapshot.filter_toggle" label="Everything"></v-switch>
							<div v-show="dialog_backup_snapshot.filter_toggle === false">
								<v-checkbox size="small" hide-details v-model="dialog_backup_snapshot.filter_options" label="Database" value="database"></v-checkbox>
 								<v-checkbox size="small" hide-details v-model="dialog_backup_snapshot.filter_options" label="Themes" value="themes"></v-checkbox>
								<v-checkbox size="small" hide-details v-model="dialog_backup_snapshot.filter_options" label="Plugins" value="plugins"></v-checkbox>
								<v-checkbox size="small" hide-details v-model="dialog_backup_snapshot.filter_options" label="Uploads" value="uploads"></v-checkbox>
								<v-checkbox size="small" hide-details v-model="dialog_backup_snapshot.filter_options" label="Everything Else" value="everything-else"></v-checkbox>
								<v-spacer><br /></v-spacer>
							</div>
						<v-btn @click="downloadBackupSnapshot()">
							Download Snapshot
						</v-btn>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_delete_user.show" scrollable width="500px">
					<v-card rounded="0">
						<v-toolbar color="primary">
							<v-btn icon="mdi-close" @click="dialog_delete_user.show = false"></v-btn>
							<v-toolbar-title>Delete user</v-toolbar-title>
							<v-spacer></v-spacer>
						</v-toolbar>
						<v-card-text>
						<v-container>
							<v-row>
								<v-col cols="12" class="pa-2">
										<span>To delete <strong>{{ dialog_delete_user.username }}</strong> from <strong>{{ dialog_delete_user.site.name }}</strong> ({{ dialog_site.environment_selected.environment }}), please reassign posts to another user.</span>
										<v-autocomplete
											:items="dialog_delete_user.users"
											return-object
											v-model="dialog_delete_user.reassign"
											item-title="user_login"
											label="Reassign posts to"
											chips
											hide-details
											hide-selected
											closable-chips
											variant="underlined"
											class="mt-4 mb-4"
										>
										</v-autocomplete>
										<v-btn @click="deleteUser()" color="primary">
											Delete User <strong>&nbsp;{{ dialog_delete_user.username }}</strong>
										</v-btn>
								</v-col>
							</v-row>
						</v-container>
						</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_share.show" max-width="600">
					<v-card rounded="xl">
						<v-toolbar color="primary" flat>
							<v-btn icon="mdi-close" @click="dialog_share.show = false"></v-btn>
							<v-toolbar-title>Share Access</v-toolbar-title>
						</v-toolbar>
						<v-card-text class="pt-5">
							<div v-if="dialog_share.loading" class="text-center py-5">
								<v-progress-circular indeterminate color="primary"></v-progress-circular>
							</div>
							<div v-else>
								<p class="text-body-2 mb-4">
									Invite a user to manage <strong>{{ dialog_share.preview.site_name }}</strong>.
								</p>
								<v-text-field
									v-model="dialog_share.email"
									label="Email Address"
									placeholder="colleague@example.com"
									variant="outlined"
									prepend-inner-icon="mdi-email"
									autofocus
									@keydown.enter="sendSiteInvite"
								></v-text-field>

								<v-expand-transition>
									<v-alert
										v-if="dialog_share.email && isValidEmail(dialog_share.email)"
										icon="mdi-information-outline"
										variant="tonal"
										color="info"
										class="mt-2 mb-4 text-body-2"
										border="start"
									>
										<!-- Scenario A: Inviter has full account access (Show specific details) -->
										<div v-if="dialog_share.preview.has_account_access">
											Inviting <strong>{{ dialog_share.email }}</strong> will grant them access to the account <strong>{{ dialog_share.preview.account_name }}</strong>. This includes:
											<ul class="ml-4 mt-2">
												<li>
													<strong>{{ dialog_share.preview.total_sites }} Website{{ dialog_share.preview.total_sites !== 1 ? 's' : '' }}:</strong> 
													<span class="text-caption text-medium-emphasis d-block" v-if="dialog_share.preview.sites_list.length > 0">
														{{ dialog_share.preview.sites_list.map(s => s.name).join(', ') }}
													</span>
												</li>
												<li v-if="dialog_share.preview.total_domains > 0">
													<strong>{{ dialog_share.preview.total_domains }} Domain{{ dialog_share.preview.total_domains !== 1 ? 's' : '' }}</strong>
												</li>
											</ul>
										</div>

										<!-- Scenario B: Inviter has shared access (Show counts only) -->
										<div v-else>
											Inviting <strong>{{ dialog_share.email }}</strong> will grant them access to <strong>{{ dialog_share.preview.site_name }}</strong>
											<span v-if="dialog_share.preview.total_sites > 1">
												along with {{ dialog_share.preview.total_sites - 1 }} other site{{ (dialog_share.preview.total_sites - 1) !== 1 ? 's' : '' }}
											</span>
											<span v-if="dialog_share.preview.total_domains > 0">
												and {{ dialog_share.preview.total_domains }} domain{{ dialog_share.preview.total_domains !== 1 ? 's' : '' }}
											</span>
											linked to this website's customer account.
										</div>
									</v-alert>
								</v-expand-transition>

								<v-alert v-if="dialog_share.error" type="error" variant="tonal" class="mt-2">
									{{ dialog_share.error }}
								</v-alert>
							</div>
						</v-card-text>
						<v-card-actions class="pa-4">
							<v-spacer></v-spacer>
							<v-btn variant="text" @click="dialog_share.show = false">Cancel</v-btn>
							<v-btn 
								color="primary" 
								@click="sendSiteInvite()" 
								:loading="dialog_share.sending"
								:disabled="!isValidEmail(dialog_share.email)"
							>
								Send Invite
							</v-btn>
						</v-card-actions>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_launch.show" width="500">
					<v-card rounded="0">
						<v-toolbar flat color="primary">
							<v-btn icon="mdi-close" @click="dialog_launch.show = false"></v-btn>
							<v-toolbar-title>Launch Site {{ dialog_launch.site.name }}</v-toolbar-title>
						</v-toolbar>
						<v-card-text>
							<v-container>
								<v-row>
									<v-col cols="12" class="pa-2">
										<span>Will turn off search privacy and update development URLs to the following live URLs.</span>
										<br />
										<v-text-field 
											label="Domain" 
											prefix="https://" 
											v-model="dialog_launch.domain"
											variant="underlined"
										></v-text-field>
										<v-btn @click="launchSite()" color="primary" class="mt-2">
											Launch Site
										</v-btn>
									</v-col>
								</v-row>
							</v-container>
						</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog :model-value="dialog_captures.show" @update:model-value="val => !val && closeCaptures()" fullscreen scrollable>
				<v-card tile>
					<v-toolbar flat dark color="primary" class="shrink">
						<v-btn icon dark @click="closeCaptures()">
							<v-icon>mdi-close</v-icon>
						</v-btn>
						<v-toolbar-title>Visual Captures of {{ dialog_site.environment_selected.home_url }}</v-toolbar-title>
					</v-toolbar>
					<v-toolbar flat class="px-4">
						<div style="max-width:250px;" class="mx-1 mt-8" v-show="dialog_captures.captures.length != 0">
							<v-select v-model="dialog_captures.capture" density="compact" variant="underlined" :items="dialog_captures.captures" item-title="created_at_friendly" item-value="capture_id" label="Taken On" return-object @update:model-value="switchCapture"></v-select>
						</div>
						<div style="min-width:150px;" class="mx-1 mt-8" v-show="dialog_captures.captures.length != 0">
							<v-select v-model="dialog_captures.selected_page" density="compact" variant="underlined" :items="dialog_captures.capture.pages" item-title="name" item-value="name" value="/" :label="`Contains ${dialog_captures.capture.pages.length} ${dialogCapturesPagesText}`" return-object @update:model-value="dialog_captures.image_loading = true"></v-select>
						</div>
						<v-spacer></v-spacer>
						<v-toolbar-items>
						<v-tooltip location="top">
							<template v-slot:activator="{ props }">
								<v-btn variant="text" @click="dialog_captures.show_configure = true" v-bind:class='{ "v-btn--active": dialog_bulk.show }' v-bind="props"><small v-show="sites_selected.length > 0">({{ sites_selected.length }})</small><v-icon>mdi-cog</v-icon></v-btn>
							</template><span>Capture configurations</span>
						</v-tooltip>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-tooltip location="top">
							<template v-slot:activator="{ props }">
                    		<v-btn variant="text" @click="captureCheck()" v-bind="props" icon="mdi-sync"></v-btn>
							</template><span>Check for new Capture</span>
						</v-tooltip>
						</v-toolbar-items>
					</v-toolbar>
					<v-card-text style="min-height:200px;">
					<v-card v-show="dialog_captures.show_configure" class="mt-5 mb-3" style="max-width:850px;margin:auto;">
						<v-toolbar density="compact" light flat>
							<v-btn icon @click="dialog_captures.show_configure = false">
								<v-icon>mdi-close</v-icon>
							</v-btn>
							<v-toolbar-title>Capture configurations</v-toolbar-title>
						</v-toolbar>
						<v-card-text>
							<v-list-subheader>Configured pages to capture</v-list-subheader>
							<v-alert variant="text" type="info">Should start with a <code>/</code>. Example use <code>/</code> for the homepage and <code>/contact</code> for the the contact page.</v-alert>
							<v-row class="mx-1">
								<v-col>
									<v-text-field v-for="item in dialog_captures.pages" label="Page URL" :model-value="item.page" @update:model-value="item.page = $event" append-outer-icon="mdi-delete" @click:append-outer="dialog_captures.pages = dialog_captures.pages.filter( p => p !== item)"></v-text-field>
								</v-col>
							</v-row>
							<p class="mx-1"><v-btn variant="text" size="small" icon color="primary" @click="addAdditionalCapturePage"><v-icon>mdi-plus-box</v-icon></v-btn></p>
							<v-list-subheader>Basic Auth</v-list-subheader>
							<v-row class="mx-1">
								<v-col>
									<v-text-field label="Username" v-model="dialog_captures.auth.username"></v-text-field>
								</v-col>
								<v-col>
									<v-text-field type="password" label="Password" v-model="dialog_captures.auth.password"></v-text-field>
								</v-col>
							</v-row>
							<p><v-btn color="primary" @click="updateCaptureConfigurations()">Update Configurations</v-btn></p>
						</v-card-text>
					</v-card>
					<v-container class="text-center" v-if="dialog_captures.captures.length > 0 && ! dialog_captures.loading">
						<div style="position: relative; display: inline-block;">
							<img 
								:src="safeUrl( `${dialog_captures.image_path}${dialog_captures.selected_page.image}` )" 
								style="max-width:100%;" 
								class="elevation-5 mt-5"
								@load="dialog_captures.image_loading = false"
								@error="dialog_captures.image_loading = false"
							>
							<v-overlay :model-value="dialog_captures.image_loading" class="align-start justify-center" contained persistent>
								<v-progress-circular indeterminate size="64" style="top: 200px;"></v-progress-circular>
							</v-overlay>
						</div>
					</v-container>
					<v-container v-show="dialog_captures.captures.length == 0 && ! dialog_captures.loading" class="mt-5">
						<v-alert variant="text" type="info">There are no visual captures, yet.</v-alert>
					</v-container>
					<v-container v-show="dialog_captures.loading" class="mt-5">
						<v-progress-linear indeterminate rounded height="6" class="mb-3"></v-progress-linear>
					</v-container>
					</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_toggle.show" width="700">
				<v-card>
					<v-toolbar color="primary" elevation="0">
						<v-btn icon="mdi-close" @click="dialog_toggle.show = false"></v-btn>
						<v-toolbar-title>Toggle Site {{ dialog_toggle.site_name }}</v-toolbar-title>
					</v-toolbar>
					<v-card-text>
					<v-container>
						<v-row>
						<v-col cols="6" pa-2>
							<v-card flat border="thin" variant="outlined">
							<v-card-text>
								<p>Will apply deactivate message with the following link back to the site owner.</p>
								<v-text-field variant="underlined" label="Business Name" v-model="dialog_toggle.business_name" class="mt-3"></v-text-field>
								<v-text-field variant="underlined" label="Business Link" v-model="dialog_toggle.business_link"></v-text-field>
								<v-btn color="primary" @click="DeactivateSite(dialog_toggle.site_id)">Deactivate Site</v-btn>
							</v-card-text>
							</v-card>
						</v-col>
						<v-col cols="6" pa-2>
							<v-card flat border="thin" variant="outlined">
							<v-card-text>
								<p>Will remove the deactivate message and allow the site to be re-activated.</p>
								<v-btn color="primary" @click="ActivateSite(dialog_toggle.site_id)" class="mt-3">Activate Site</v-btn>
							</v-card-text>
							</v-card>
						</v-col>
						</v-row>
					</v-container>
					</v-card-text>
				</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_migration.show" width="500">
					<v-card rounded="0">
						<v-toolbar flat color="primary">
							<v-btn icon="mdi-close" @click="dialog_migration.show = false"></v-btn>
							<v-toolbar-title>Migrate from backup to {{ dialog_migration.site_name }}</v-toolbar-title>
						</v-toolbar>
						<v-card-text>
							<v-alert variant="tonal" type="info" color="yellow-darken-4" class="mt-3">
								Warning {{ dialog_migration.site_name }} will be overwritten with backup.
							</v-alert>
							<v-form ref="formSiteMigration" class="mt-4">
								<v-text-field 
									:rules="[v => !!v || 'Backup URL is required']" 
									required 
									label="Backup URL" 
									placeholder="https://storage.googleapis.com/..../live-backup.zip" 
									v-model="dialog_migration.backup_url"
									variant="underlined"
								></v-text-field>
								<v-checkbox 
									label="Update URLs" 
									v-model="dialog_migration.update_urls" 
									hint="Will change urls in database to match the existing site." 
									persistent-hint
								></v-checkbox>
								<v-btn @click="validateSiteMigration" color="primary" class="mt-4">
									Start Migration
								</v-btn>
							</v-form>
						</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_copy_site.show" width="500">
					<v-card rounded="0">
						<v-toolbar flat color="primary">
							<v-btn icon="mdi-close" @click="dialog_copy_site.show = false"></v-btn>
							<v-toolbar-title>Copy Site {{ dialog_copy_site.site.name }} to...</v-toolbar-title>
						</v-toolbar>
						<v-card-text>
							<v-autocomplete
								:items="dialog_copy_site.options"
								v-model="dialog_copy_site.destination"
								label="Select Destination Site"
								item-title="name"
								item-value="id"
								variant="underlined"
								chips
								closable-chips
							></v-autocomplete>
							<v-btn @click="startCopySite()" color="primary" class="mt-3">
								Copy Site
							</v-btn>
						</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_apply_https_urls.show" width="500">
					<v-card rounded="0">
						<v-toolbar flat color="primary">
							<v-btn icon="mdi-close" @click="dialog_apply_https_urls.show = false"></v-btn>
							<v-toolbar-title>Apply HTTPS URLs for {{ dialog_apply_https_urls.site_name }}</v-toolbar-title>
						</v-toolbar>
						<v-card-text>
							<v-container>
								<v-alert variant="tonal" type="info" class="mb-4">
									Domain needs to match current home URL. Otherwise, server domain mapping will need to be updated to prevent a redirection loop.
								</v-alert>
								<div class="text-subtitle-1 mb-3">Select URL replacement option:</div>
								<v-btn color="primary" @click="applyHttpsUrls('apply-https')" class="mb-3" block>
									Option 1: https://domain.tld
								</v-btn>
								<v-btn color="primary" @click="applyHttpsUrls('apply-https-with-www')" block>
									Option 2: https://www.domain.tld
								</v-btn>
							</v-container>
						</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_file_diff.show" scrollable>
					<v-card rounded="0">
						<v-toolbar flat color="primary">
							<v-btn icon="mdi-close" @click="dialog_file_diff.show = false"></v-btn>
							<v-toolbar-title>File diff {{ dialog_file_diff.file_name }}</v-toolbar-title>
							<v-spacer></v-spacer>
							
							<v-btn variant="text" @click="QuicksaveFileRestore()" class="d-none d-md-flex">
								Restore this file
							</v-btn>

						</v-toolbar>
						<v-card-text>
							<v-container v-show="dialog_file_diff.loading" class="mt-5">
								<v-progress-linear indeterminate height="6" color="primary"></v-progress-linear>
							</v-container>
							<v-container id="code_diff" v-html="dialog_file_diff.response">
							</v-container>
						</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="dialog_bulk_tools.show" fullscreen scrollable>
					<v-card rounded="0">
						<v-toolbar flat color="primary">
							<v-btn icon="mdi-close" @click="dialog_bulk_tools.show = false"></v-btn>
							<v-toolbar-title>Bulk Tools</v-toolbar-title>
						</v-toolbar>
						<v-toolbar flat color="transparent" class="ma-2 px-4">
							<span class="mr-2 text-body-2">Run on:</span>
							<v-select variant="outlined" v-model="dialog_bulk_tools.environment_selected" :items='[{"name":"Production Environment","value":"Production"},{"name":"Staging Environment","value":"Staging"}]' item-title="name" item-value="value" class="mx-1 mt-6" solo density="compact" chips small-chips style="max-width:240px;"></v-select>
							<v-autocomplete variant="outlined" v-model="sites_selected" :items="sites" item-title="name" return-object density="compact" label="Search" multiple class="mx-1 mt-6" style="max-width: 240px;">
								<template v-slot:selection="{ item, index }">
									<div v-if="index === 0" class="d-flex align-center" style="white-space: nowrap;">
										<v-chip class="me-1" size="small">
											{{ item.title }}
										</v-chip>
										<span v-if="sites_selected.length > 1" class="text-caption">
											+{{ sites_selected.length - 1 }}
										</span>
									</div>
								</template>
							</v-autocomplete>
							<v-btn size="small" variant="tonal" class="mx-1" @click="sites_selected = []; snackbar.message = 'Selections cleared.'; snackbar.show = true" v-show="sites_selected && sites_selected.length > 0">Clear Selections</v-btn>
							<v-btn size="small" variant="tonal" class="mx-1" v-show="filterCount" @click="sites_selected = sites.filter( s => s.filtered )">Select {{ sites.filter( s => s.filtered ).length }} sites in applied filters</v-btn>
							<v-btn size="small" variant="tonal" class="mx-1" @click="sites_selected = sites">Select all {{ sites.length }} sites</v-btn>
							<v-spacer></v-spacer>
							<v-tooltip location="top">
								<template v-slot:activator="{ props }">
									<v-btn icon="mdi-plus" @click="addThemeBulk()" v-bind="props"></v-btn>
								</template>
								<span>Add theme</span>
							</v-tooltip>
							<v-tooltip location="top">
								<template v-slot:activator="{ props }">
								<v-btn icon="mdi-plus" @click="addPluginBulk()" v-bind="props"></v-btn>
								</template>
								<span>Add plugin</span>
							</v-tooltip>
							<v-tooltip location="top" v-if="role == 'administrator'">
								<template v-slot:activator="{ props }">
								<v-btn icon="mdi-checkbox-marked" @click="showLogEntryBulk()" v-bind="props"></v-btn>
								</template>
								<span>New Log Entry</span>
							</v-tooltip>
							<v-tooltip location="top">
								<template v-slot:activator="{ props }">
								<v-btn icon="mdi-open-in-new" @click="bulkactionLaunch()" v-bind="props"></v-btn>
								</template>
								<span>Open websites in browser</span>
							</v-tooltip>
							<v-tooltip location="top">
								<template v-slot:activator="{ props }">
								<v-btn icon="mdi-sync" @click="bulkSyncSites()" v-bind="props"></v-btn>
								</template>
								<span>Manual sync website details</span>
							</v-tooltip>
						</v-toolbar>
						<v-card-text>
							<v-row>
								<v-col cols="12" md="4" class="py-0 my-0">
								<small>Common Scripts</small><br />
								<v-tooltip location="top">
									<template v-slot:activator="{ props }">
									<v-btn variant="text" size="small" icon="mdi-rocket-launch" @click="viewApplyHttpsUrlsBulk()" v-bind="props"></v-btn>
									</template>
									<span>Apply HTTPS Urls</span>
								</v-tooltip>
								<v-tooltip location="top">
									<template v-slot:activator="{ props }">
									<v-btn variant="text" size="small" icon="mdi-refresh" @click="siteDeployBulk()" v-bind="props"></v-btn>
									</template>
									<span>Deploy Defaults</span>
								</v-tooltip>
								<v-tooltip location="top">
									<template v-slot:activator="{ props }">
									<v-btn variant="text" size="small" icon="mdi-toggle-switch" @click="toggleSiteBulk()" v-bind="props"></v-btn>
									</template>
									<span>Toggle Site</span>
								</v-tooltip><br />
								<small>Other Scripts</small><br />
								<v-tooltip location="top" density="compact" v-for="recipe in recipes.filter( r => r.public == 1 )">
									<template v-slot:activator="{ props }">
									<v-btn variant="text" size="small" icon="mdi-script-text-outline" @click="runRecipeBulk( recipe.recipe_id )" v-bind="props"></v-btn>
									</template>
									<span>{{ recipe.title }}</span>
								</v-tooltip><br />
								<small><span v-show="sites_selected.length > 0">Selected sites: </span>
									<span v-for="site in sites_selected" style="display: inline-block;" v-if="dialog_bulk_tools.environment_selected == 'Production' || dialog_bulk_tools.environment_selected == 'Both'">{{ site.site }}&nbsp;</span>
									<span v-for="site in sites_selected" style="display: inline-block;" v-if="dialog_bulk_tools.environment_selected == 'Staging' || dialog_bulk_tools.environment_selected == 'Both'">{{ site.site }}-staging&nbsp;</span>
								</small>
								</v-col>
								<v-col cols="12" md="8" class="py-0 my-0">
								<v-textarea variant="outlined" auto-grow solo rows="8" dense hint="Custom bash script or WP-CLI commands" persistent-hint :model-value="script.code" @update:model-value="script.code = $event" spellcheck="false" class="code">
									<template v-slot:append-inner>
									<div style="display: flex; align-items: flex-end; height: 100%;" class="pb-3">
										<v-btn size="small" color="primary" dark @click="runCustomCodeBulk()">Run Custom Code</v-btn>
									</div>
									</template>
								</v-textarea>
								</v-col>
							</v-row>
						</v-card-text>
					</v-card>
				</v-dialog>
				<v-dialog v-model="mailgun.subdomainDialog" max-width="500px">
				<v-card>
					<v-card-title>Activate Mailgun for {{ mailgun.activeDomain.name }}</v-card-title>
					<v-card-text>
					<p>Enter a subdomain to use for Mailgun. This will create 'mg.{{ mailgun.activeDomain.name }}' by default.</p>
					<v-form v-model="mailgun.validSubdomain">
						<v-text-field
						v-model="mailgun.subdomain"
						label="Subdomain"
						:suffix="'.' + mailgun.activeDomain.name"
						:rules="mailgun.subdomainRules"
						required
						></v-text-field>
					</v-form>
					</v-card-text>
					<v-card-actions>
					<v-spacer></v-spacer>
					<v-btn text @click="mailgun.subdomainDialog = false">Cancel</v-btn>
					<v-btn color="blue darken-1" @click="submitMailgunActivation" :disabled="!mailgun.validSubdomain">Submit</v-btn>
					</v-card-actions>
				</v-card>
				</v-dialog>
				<v-dialog v-model="mailgun.deployDialog" max-width="500px">
				<v-card>
					<v-card-title>Deploy Gravity SMTP</v-card-title>
					<v-card-text>
					<v-form v-model="mailgun.deployFormValid" ref="mailgunDeployForm">
						<p>Configure the "Send From Name" for {{ mailgun.activeSite.name }}.</p>
						<v-text-field
							v-model="mailgun.deployName"
							label="Send From Name"
							:rules="[v => !!v || 'Send From Name is required']"
							required
							variant="underlined"
							class="mt-2"
						></v-text-field>
					</v-form>
					</v-card-text>
					<v-card-actions>
					<v-spacer></v-spacer>
					<v-btn text @click="mailgun.deployDialog = false">Cancel</v-btn>
					<v-btn color="blue darken-1" @click="submitMailgunDeploy">Deploy</v-btn>
					</v-card-actions>
				</v-card>
				</v-dialog>
			<v-container fluid v-show="loading_page != true" style="padding:0px;">
			<v-card rounded="0" flat v-if="route == 'login'" color="transparent" class="fill-height d-flex align-center justify-center">

				<!-- Invite Acceptance Screen -->
				<div v-if="fetchInvite.account" class="w-100 py-10">
					<div class="text-center mb-8">
						<v-alert
							color="primary"
							variant="tonal"
							class="d-inline-block text-left"
							style="max-width: 600px;"
							border="start"
							density="comfortable"
						>
							<template v-slot:prepend>
								<v-icon icon="mdi-information"></v-icon>
							</template>
							To accept the invitation for this account, please either <strong>create a new account</strong> or <strong>login</strong> to an existing account.
						</v-alert>
					</div>

					<v-row justify="center" class="ma-0">
						<!-- Create Account Column -->
						<v-col cols="12" md="6" lg="4" class="d-flex justify-center justify-md-end px-4 mb-4 mb-md-0">
							<v-card style="width: 100%; max-width: 380px;" flat border="thin" rounded="xl" class="d-flex flex-column">
								<v-toolbar flat color="transparent" density="comfortable" class="border-b">
									<v-toolbar-title class="text-h6 font-weight-regular pl-4">Create new account</v-toolbar-title>
								</v-toolbar>
								<v-card-text class="pt-6 pb-6 px-5 flex-grow-1">
									<div class="text-caption text-medium-emphasis mb-1">Email</div>
									<v-text-field 
										readonly 
										value="################" 
										hint="Will use email where invite was sent to." 
										persistent-hint 
										variant="outlined"
										density="compact"
										bg-color="grey-lighten-5"
										class="mb-4"
										hide-details="auto"
									></v-text-field>

									<div class="text-caption text-medium-emphasis mb-1">Password</div>
									<v-text-field 
										type="password" 
										v-model="new_account.password" 
										variant="outlined" 
										density="compact"
										hide-details
									></v-text-field>
									
									<v-btn 
										color="primary" 
										@click="createAccount()" 
										block 
										size="large"
										class="mt-6 font-weight-bold"
										flat
									>
										Create Account
									</v-btn>
								</v-card-text>
							</v-card>
						</v-col>

						<!-- Login Column -->
						<v-col cols="12" md="6" lg="4" class="d-flex justify-center justify-md-start px-4">
							<v-card style="width: 100%; max-width: 380px;" flat border="thin" rounded="xl">
								<v-toolbar flat color="transparent" density="comfortable" class="border-b">
									<v-toolbar-title class="text-h6 font-weight-regular pl-4">Login</v-toolbar-title>
								</v-toolbar>
								<v-card-text class="pt-6 pb-2 px-5">
									<!-- Reset Password Form -->
									<v-form v-if="login.lost_password" ref="reset_invite" @submit.prevent="resetPassword()">
										<div class="text-caption text-medium-emphasis mb-1">Username or Email</div>
										<v-text-field 
											:model-value="login.user_login" 
											@update:model-value="login.user_login = $event" 
											required 
											:disabled="login.loading" 
											:rules="[v => !!v || 'Username is required']" 
											variant="outlined" 
											density="compact"
											class="mb-4"
										></v-text-field>

										<v-alert variant="tonal" type="success" v-show="login.message" density="compact" class="mb-4">{{ login.message }}</v-alert>
										<v-progress-linear indeterminate rounded height="4" class="mb-4" v-show="login.loading" color="primary"></v-progress-linear>
										
										<v-btn color="primary" type="submit" :disabled="login.loading" block size="large" flat class="font-weight-bold">Reset Password</v-btn>
									</v-form>
									
									<!-- Login Form -->
									<v-form lazy-validation ref="login_invite" @submit.prevent="signIn()" v-else>
										<div class="text-caption text-medium-emphasis mb-1">Username or Email</div>
										<v-text-field 
											v-model="login.user_login" 
											required 
											:disabled="login.loading" 
											:rules="[v => !!v || 'Username is required']" 
											variant="outlined" 
											density="compact"
											class="mb-4"
											hide-details="auto"
										></v-text-field>
										
										<div class="text-caption text-medium-emphasis mb-1">Password</div>
										<v-text-field 
											v-model="login.user_password" 
											required 
											:disabled="login.loading" 
											type="password" 
											:rules="[v => !!v || 'Password is required']" 
											variant="outlined" 
											density="compact"
											class="mb-4"
											hide-details="auto"
										></v-text-field>

										<div v-show="login.info || login.errors == 'One time password is invalid.'" class="mb-4">
											<div class="text-caption text-medium-emphasis mb-1">One Time Password</div>
											<v-otp-input 
												length="6" 
												type="number" 
												v-model="login.tfa_code" 
												required 
												:disabled="login.loading"
												variant="outlined"
												density="compact"
												min-height="40"
											></v-otp-input>
										</div>

										<v-alert variant="tonal" type="error" v-show="login.errors" density="compact" class="mb-4">{{ login.errors }}</v-alert>
										<v-alert variant="tonal" type="info" v-show="login.info" density="compact" class="mb-4">{{ login.info }}</v-alert>
										<v-progress-linear indeterminate rounded height="4" class="mb-4" v-show="login.loading" color="primary"></v-progress-linear>

										<v-btn color="primary" type="submit" :disabled="login.loading" block size="large" flat class="font-weight-bold">Login</v-btn>
									</v-form>
								</v-card-text>
								<div class="px-5 pb-6 text-center">
									<a href="#reset" @click.prevent="login.lost_password = true" class="text-caption text-decoration-underline text-primary" v-show="!login.lost_password">Lost your password?</a>
									<a href="#login" @click.prevent="login.lost_password = false" class="text-caption text-decoration-underline text-primary" v-show="login.lost_password">Back to login form.</a>
								</div>
							</v-card>
						</v-col>
					</v-row>
				</div>

				<!-- Standalone Login (Standard) -->
				<template v-else>
					<v-card style="max-width: 358px; width: 100%; margin: auto;" flat border="thin" rounded="xl">
						<v-toolbar flat color="transparent" density="comfortable">
							<v-toolbar-title class="text-h6 font-weight-regular pl-4">Login</v-toolbar-title>
							<v-spacer></v-spacer>
						</v-toolbar>
						<v-divider></v-divider>
						<v-card-text class="my-2 px-5 py-6">
						<v-form v-if="login.lost_password" @submit.prevent="resetPassword()" ref="reset">
						<v-row dense>
							<v-col cols="12">
								<div class="text-caption text-medium-emphasis mb-1">Username or Email</div>
								<v-text-field :model-value="login.user_login" @update:model-value="login.user_login = $event" required :disabled="login.loading" :rules="[v => !!v || 'Username is required']" variant="outlined" density="compact" hide-details="auto" class="mb-4"></v-text-field>
							</v-col>
							<v-col cols="12">
								<v-alert variant="tonal" type="success" v-show="login.message" density="compact" class="mb-4">{{ login.message }}</v-alert>
							</v-col>
							<v-col cols="12">
								<v-progress-linear indeterminate rounded height="4" class="mb-4" v-show="login.loading" color="primary"></v-progress-linear>
								<v-btn color="primary" type="submit" :disabled="login.loading" block size="large" flat class="font-weight-bold">Reset Password</v-btn>
							</v-col>
						</v-row>
						</v-form>
						<v-form lazy-validation ref="login" @submit.prevent="signIn()" v-else>
						<v-row dense>
							<v-col cols="12">
								<div class="text-caption text-medium-emphasis mb-1">Username or Email</div>
								<v-text-field v-model="login.user_login" required :disabled="login.loading" :rules="[v => !!v || 'Username is required']" variant="outlined" density="compact" hide-details="auto" class="mb-4"></v-text-field>
								
								<div class="text-caption text-medium-emphasis mb-1">Password</div>
								<v-text-field v-model="login.user_password" required :disabled="login.loading" type="password" :rules="[v => !!v || 'Password is required']" variant="outlined" density="compact" hide-details="auto" class="mb-4"></v-text-field>
							</v-col>
							<v-col cols="12" v-show="login.info || login.errors == 'One time password is invalid.'" class="py-0">
								<div class="text-caption text-medium-emphasis mb-1">One Time Password</div>
								<div class="d-flex justify-start mb-4">
									<v-otp-input length="6" type="number" v-model="login.tfa_code" required :disabled="login.loading" variant="outlined" density="compact" min-height="40"></v-otp-input>
								</div>
							</v-col>
							<v-col cols="12">
								<v-alert variant="tonal" type="error" v-show="login.errors" density="compact" class="mb-4">{{ login.errors }}</v-alert>
								<v-alert variant="tonal" type="info" v-show="login.info" density="compact" class="mb-4">{{ login.info }}</v-alert>
							</v-col>
							<v-col cols="12">
								<v-progress-linear indeterminate rounded height="4" class="mb-4" v-show="login.loading" color="primary"></v-progress-linear>
								<v-btn color="primary" type="submit" :disabled="login.loading" block size="large" flat class="font-weight-bold">Login</v-btn>
							</v-col>
						</v-row>
						</v-form>
						</v-card-text>
						<div class="px-5 pb-6 text-center">
							<a href="#reset" @click.prevent="login.lost_password = true" class="text-caption text-decoration-underline text-primary" v-show="!login.lost_password">Lost your password?</a>
							<a href="#login" @click.prevent="login.lost_password = false" class="text-caption text-decoration-underline text-primary" v-show="login.lost_password">Back to login form.</a>
						</div>
					</v-card>
				</template>
			</v-card>
			<v-card v-if="route == 'sites'" id="sites" flat border="thin" rounded="xl">
			<v-toolbar v-show="dialog_site.step == 1 && sites.length > 0" id="site_listings" flat color="transparent">
				<v-toolbar-title>
					<span v-if="sites_loading">Searching...</span>
					<span v-else-if="isAnySiteFilterActive">
						Showing {{ filteredSites.length }} sites
						<small class="text-caption text-medium-emphasis ml-1">({{ filteredEnvironmentsCount }} environments)</small>
					</span>
					<span v-else>
						Listing {{ sites.length }} sites
						<small class="text-caption text-medium-emphasis ml-1">({{ totalEnvironmentsCount }} environments)</small>
					</span>
				</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items>
					<v-btn-toggle v-model="toggle_site" mandatory variant="outlined" divided class="mr-2 align-self-center" color="primary" density="comfortable">
						<v-btn value="cards" icon="mdi-card-text" title="Command Center"></v-btn>
						<v-btn value="table" icon="mdi-table" title="Table View"></v-btn>
						<v-btn value="grid" icon="mdi-view-grid" title="Thumbnail View"></v-btn>
					</v-btn-toggle>

					<v-tooltip location="top">
						<template v-slot:activator="{ props }">
							<v-btn icon="mdi-console" @click="view_console.terminal_open = !view_console.terminal_open; view_console.show = true" v-bind="props"></v-btn>
						</template>
						<span>Terminal Console</span>
					</v-tooltip>
					<v-menu open-on-hover text bottom offset-y>
						<template v-slot:activator="{ props }">
							<v-btn v-bind="props" text>
								Add Site <v-icon dark>mdi-plus</v-icon>
							</v-btn>
						</template>
						<v-list>
							<v-list-item @click="dialog_request_site.show = true; dialog_request_site.request.account_id = accounts[0].account_id">
								<v-list-item-title>Request new site</v-list-item-title>
							</v-list-item>
							<v-list-item @click="showNewSiteKinsta()">
								<v-list-item-title>Create new site</v-list-item-title>
								<template v-slot:append>
									<v-icon><v-img src="/wp-content/plugins/captaincore-manager/public/img/kinsta-icon.svg" max-width="20px"></v-img></v-icon>
								</template>
							</v-list-item>
							<v-list-item @click="goToPath( `/sites/new` )" href v-show="role == 'administrator' || role == 'owner'">
								<v-list-item-title class="mr-4">Manually connect</v-list-item-title>
								<template v-slot:append>
									<v-icon icon="mdi-console-network"></v-icon>
								</template>
							</v-list-item>
						</v-list>
					</v-menu>
				</v-toolbar-items>
			</v-toolbar>
			<v-sheet v-show="dialog_site.step == 1" color="transparent">
				<v-dialog v-model="dialog_new_site_kinsta.show" width="550">
				<v-card>
					<v-toolbar elevation="0" color="primary">
						<v-btn icon @click="dialog_new_site_kinsta.show = false">
							<v-icon>mdi-close</v-icon>
						</v-btn>
						<v-toolbar-title class="pl-2">New WordPress Site</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>
					<v-card-text>
						<v-alert type="error" v-for="error in dialog_new_site_kinsta.errors">{{ error }}</v-alert>
						<v-text-field label="Name" v-model="dialog_new_site_kinsta.site.name" variant="underlined"></v-text-field>
						<v-text-field label="Domain" v-model="dialog_new_site_kinsta.site.domain" variant="underlined"></v-text-field>
						<v-autocomplete variant="underlined" label="Hosting Provider" v-model="dialog_new_site_kinsta.site.provider_id" item-value="provider_id" :item-title="formatProviderLabel" :items="kinsta_providers" v-show="kinsta_providers.length > 1" @update:model-value="populateCloneSites"></v-autocomplete>
						<v-autocomplete variant="underlined" label="Datacenter" v-model="dialog_new_site_kinsta.site.datacenter" :items="datacenters" hint="Use 🚀 for the fastest servers" persistent-hint v-show="dialog_new_site_kinsta.site.clone_site_id == ''"></v-autocomplete>
						<v-autocomplete variant="underlined" label="Clone Existing Site" v-model="dialog_new_site_kinsta.site.clone_site_id" item-value="id" item-title="display_name" hide-no-data hide-selected :items="clone_sites" clearable v-show="kinsta_providers.length > 1"></v-autocomplete>
						<v-autocomplete
							v-if="role == 'administrator'"
							:items="accounts"
							v-model="dialog_new_site_kinsta.site.shared_with"
							label="Assign to an account"
							item-title="name"
							item-value="account_id"
							chips
							closable-chips
							multiple
							return-object
							class="mt-2"
							hint="If a customer account is not assigned then site will be placed in a new account."
							persistent-hint
							:menu-props="{ closeOnContentClick:true, openOnClick: false }"
							variant="underlined"
						></v-autocomplete>
						<div v-else>
							<v-select variant="underlined" class="mt-3 mb-3" hide-details v-model="dialog_new_site_kinsta.site.account_id" label="Billing Account" :items="accounts" item-title="name" item-value="account_id"></v-select>
							<v-select variant="underlined" clearable v-model="dialog_new_site_kinsta.site.customer_id" label="Customer Account" :items="accounts" item-title="name" item-value="account_id" hint="If a customer account is not assigned then site will be placed in a new account." persistent-hint></v-select>
						</div>
						<v-expand-transition>
							<v-row density="compact" v-if="role == 'administrator' && dialog_new_site_kinsta.site.shared_with && dialog_new_site_kinsta.site.shared_with.length > 0" class="mt-3">
								<v-col v-for="account in dialog_new_site_kinsta.site.shared_with" :key="account.account_id" cols="6">
									<v-card>
										<v-list-item :title="account.name"></v-list-item>
										<v-card-actions class="py-0">
											<v-tooltip location="top">
												<template v-slot:activator="{ props }">
													<v-btn-toggle v-model="dialog_new_site_kinsta.site.customer_id" color="primary" group>
														<v-btn variant="text" icon="mdi-account-circle" :value="account.account_id" v-bind="props"></v-btn>
													</v-btn-toggle>
												</template>
												<span>Set as customer contact</span>
											</v-tooltip>
											<v-tooltip location="top">
												<template v-slot:activator="{ props }">
													<v-btn-toggle v-model="dialog_new_site_kinsta.site.account_id" color="primary" group>
														<v-btn variant="text" icon="mdi-currency-usd" :value="account.account_id" v-bind="props"></v-btn>
													</v-btn-toggle>
												</template>
												<span>Set as billing contact</span>
											</v-tooltip>
										</v-card-actions>
									</v-card>
								</v-col>
							</v-row>
						</v-expand-transition>
						<v-card elevation="0" v-show="dialog_new_site_kinsta.verifing">
							<v-card-text>
								Verifying Kinsta connection
								<v-progress-linear indeterminate rounded height="6"></v-progress-linear>
							</v-card-text>
						</v-card>
						<v-card elevation="0" v-show="! dialog_new_site_kinsta.verifing && ! dialog_new_site_kinsta.connection_verified">
							<v-card-text>
								<v-alert type="error">
									Kinsta token outdated 
									<v-text-field label="Token" v-model="dialog_new_site_kinsta.kinsta_token" variant="underlined" single-line>
										<template v-slot:append>
											<v-btn @click="connectKinsta">Connect</v-btn>
										</template>
									</v-text-field>
								</v-alert>
							</v-card-text>
						</v-card>
					</v-card-text>
					<v-divider></v-divider>
					<v-card-actions>
						<v-spacer></v-spacer>
						<v-btn color="primary" @click="newKinstaSite" :disabled="dialog_new_site_kinsta.verifing || ! dialog_new_site_kinsta.connection_verified">Create Site</v-btn>
					</v-card-actions>
				</v-card>
				</v-dialog>
				<v-card-text v-show="requested_sites.length > 0">
				<v-dialog v-model="dialog_site_request.show" width="500">
					<v-card>
						<v-toolbar color="primary" density="compact" class="px-4">Update site request</v-toolbar>
						<v-card-text>
						<v-text-field
							label="New Site URL"
							v-model="dialog_site_request.request.url"
							variant="underlined"
						></v-text-field>
						<v-text-field
							label="Name"
							v-model="dialog_site_request.request.name"
							variant="underlined"
						></v-text-field>
						<v-textarea
							label="Notes"
							v-model="dialog_site_request.request.notes"
							variant="underlined"
						></v-textarea>
						</v-card-text>
						<v-divider></v-divider>
						<v-card-actions>
						<v-spacer></v-spacer>
						<v-btn @click="dialog_site_request.show = false">Cancel</v-btn>
						<v-btn color="primary" @click="updateRequestSite">Save</v-btn>
						</v-card-actions>
					</v-card>
					</v-dialog>
				<v-stepper :model-value="request.step" v-for="(request, index) in requested_sites" class="mb-3">
				<v-toolbar elevation="0" color="primary" class="text-white px-3" density="compact">
					<div v-if="role == 'administrator'">Requested by {{ user_name( request.user_id ) }} -&nbsp;</div><strong>{{ request.name }}</strong>&nbsp;in {{ account_name( request.account_id ) }}
					<v-spacer></v-spacer>
					<v-btn size="small" @click="modifyRequest( index )" v-show="role == 'administrator'" class="mx-1" variant="tonal">Modify</v-btn>
					<v-btn size="small" @click="finishRequest( index )" v-if="request.step === 3" class="mx-1" variant="tonal">Finish</v-btn>
					<v-btn size="small" @click="cancelRequest( index )" v-else class="mx-1" variant="tonal">Cancel</v-btn>
				</v-toolbar>
				<v-stepper-header class="elevation-0">
					<v-stepper-item value="1" :complete="request.step > 0" color="primary" class="text-left">Requesting site<br /><small>{{ pretty_timestamp_epoch ( request.created_at ) }}</small></v-stepper-item>
					<v-divider></v-divider>
					<v-stepper-item value="2" :complete="request.step > 1" color="primary" class="text-left">Preparing new site<br /><small v-show="request.processing_at">{{ pretty_timestamp_epoch ( request.processing_at ) }}</small></v-stepper-item>
					<v-divider></v-divider>
					<v-stepper-item value="3" :complete="request.step > 2" color="primary" class="text-left">Ready to use<br /><small v-show="request.ready_at">{{ pretty_timestamp_epoch ( request.ready_at ) }}</small></v-stepper-item>
				</v-stepper-header>
				<v-stepper-window>
					<v-stepper-window-item value="1">
						<div>{{ request.notes }}</div>
						<v-btn color="primary" @click="continueRequestSite( request )" v-show="role == 'administrator'">
							Continue
						</v-btn>
					</v-stepper-window-item>
					<v-stepper-window-item value="2">
						<div v-show="role == 'administrator'">
							<v-btn @click="backRequestSite( request )" variant="text">
								Back
							</v-btn>
							<v-btn color="primary" @click="continueRequestSite( request )">
								Continue
							</v-btn>
						</div>
					</v-stepper-window-item>
					<v-stepper-window-item value="3">
						<v-card v-if="typeof request.url == 'string' && request.url != ''" elevation="2" class="ma-2">
							<v-list density="compact">
								<v-list-item :href="request.url" target="_blank" density="compact" :subtitle="request.url">
									<v-list-item-title>Link</v-list-item-title>
									<template v-slot:append>
										<v-icon>mdi-open-in-new</v-icon>
									</template>
								</v-list-item>
							</v-list>
						</v-card>
						<div v-show="role == 'administrator'">
							<v-btn @click="backRequestSite( request )" variant="text">
								Back
							</v-btn>
							<v-btn color="primary" @click="continueRequestSite( request )">
								Continue
							</v-btn>
						</div>
					</v-stepper-window-item>
				</v-stepper-window>
			</v-stepper>
				</v-card-text>
				<v-card-text v-if="sites.length == 0 && configurations.mode == 'hosting'" class="text-center ma-auto" style="max-width: 500px;">
					<v-card flat @click="dialog_request_site.show = true; dialog_request_site.request.account_id = accounts[0].account_id" class="pa-5">
						<v-img src="/wp-content/plugins/captaincore-manager/public/boat-island-line-illustration.webp" max-width="300px" class="mx-auto"></v-img>
						<a class="subtitle-1 px-5 py-3" :style="{ backgroundColor: 'rgb(var(--v-theme-accent))' }">Add your first WordPress site</a>
					</v-card>
				</v-card-text>
				<v-card-text v-if="sites.length == 0 && configurations.mode == 'maintenance'" class="text-center ma-auto" style="max-width: 500px;">
					<v-card flat @click="goToPath( `/sites/new` )" class="pa-5">
						<v-img src="/wp-content/plugins/captaincore-manager/public/boat-island-line-illustration.webp" max-width="300px" class="mx-auto"></v-img>
						<a class="subtitle-1 px-5 py-3" :style="{ backgroundColor: 'rgb(var(--v-theme-accent))' }">Add your first WordPress site</a>
					</v-card>
				</v-card-text>
				<v-card-text v-show="sites.length > 0">
					<v-row align="center" justify="end" class="my-1">
						<v-col cols="12" md="auto" class="d-flex flex-wrap align-center justify-end gap-2">
						<v-btn
							:variant="isUnassignedFilterActive ? 'flat' : 'text'"
							:color="isUnassignedFilterActive ? 'warning' : 'medium-emphasis'"
							size="small"
							rounded="pill"
							@click="toggleUnassignedFilter()"
							v-if="role == 'administrator'"
							class="mr-2 mb-1"
						>
							<v-icon start icon="mdi-alert-circle-outline"></v-icon>
							{{ unassignedSiteCount }} Unassigned
						</v-btn>

						<!-- Core Filter -->
						<v-menu offset-y v-model="coreFilterMenu" :close-on-content-click="false">
							<template v-slot:activator="{ props }">
								<v-btn
									v-bind="props"
									size="small"
									rounded="pill"
									class="mr-2 mb-1"
									:variant="coreFiltersApplied ? 'tonal' : 'text'"
									:color="coreFiltersApplied ? 'primary' : 'medium-emphasis'"
								>
									<v-icon start>mdi-wordpress</v-icon>
									Core
									<v-icon end>mdi-chevron-down</v-icon>
								</v-btn>
							</template>
							<v-card width="350" rounded="xl" elevation="4">
								<v-card-text>
									<v-autocomplete
										:model-value="applied_core_filters"
										@update:model-value="updatePrimaryFilters('core', $event)"
										:items="site_filters_core"
										item-title="name"
										label="Select Version"
										variant="outlined"
										density="compact"
										autofocus
										return-object
										multiple
										chips
										closable-chips
										hide-details
										color="primary"
									>
										<template v-slot:item="{ item, props }">
											<v-list-item v-bind="props" :title="item.raw.name">
												<template v-slot:append>
													<v-chip size="x-small" label class="ml-2">{{ item.raw.count }} sites</v-chip>
												</template>
											</v-list-item>
										</template>
									</v-autocomplete>
								</v-card-text>
							</v-card>
						</v-menu>

						<!-- Theme Filter -->
						<v-menu offset-y v-model="themeFilterMenu" :close-on-content-click="false">
							<template v-slot:activator="{ props }">
								<v-btn
									v-bind="props"
									size="small"
									rounded="pill"
									class="mr-2 mb-1"
									:variant="themeFiltersApplied ? 'tonal' : 'text'"
									:color="themeFiltersApplied ? 'primary' : 'medium-emphasis'"
								>
									<v-icon start>mdi-palette</v-icon>
									Theme
									<v-icon end>mdi-chevron-down</v-icon>
								</v-btn>
							</template>
							<v-card width="350" rounded="xl" elevation="4">
								<v-card-text>
									<v-autocomplete
										:model-value="applied_theme_filters"
										@update:model-value="updatePrimaryFilters('themes', $event)"
										:items="site_filters.filter(f => f.type === 'themes')"
										item-title="search"
										label="Select Theme"
										variant="outlined"
										density="compact"
										autofocus
										return-object
										multiple
										chips
										closable-chips
										hide-details
										color="primary"
									></v-autocomplete>
								</v-card-text>
							</v-card>
						</v-menu>

						<v-menu offset-y v-model="pluginFilterMenu" :close-on-content-click="false">
							<template v-slot:activator="{ props }">
								<v-btn
									v-bind="props"
									size="small"
									rounded="pill"
									class="mr-2 mb-1"
									:variant="pluginFiltersApplied ? 'tonal' : 'text'"
									:color="pluginFiltersApplied ? 'primary' : 'medium-emphasis'"
								>
									<v-icon start>mdi-power-plug</v-icon>
									Plugin
									<v-icon end>mdi-chevron-down</v-icon>
								</v-btn>
							</template>
							<v-card width="350" rounded="xl" elevation="4">
								<v-card-text>
									<v-autocomplete
										:model-value="applied_plugin_filters"
										@update:model-value="updatePrimaryFilters('plugins', $event)"
										:items="site_filters.filter(f => f.type === 'plugins')"
										item-title="search"
										label="Select Plugin"
										variant="outlined"
										density="compact"
										autofocus
										return-object
										multiple
										chips
										closable-chips
										hide-details
										color="primary"
									></v-autocomplete>
								</v-card-text>
							</v-card>
						</v-menu>

						<v-btn-toggle
							v-if="totalAdvancedFilters > 1"
							v-model="filter_logic"
							mandatory
							density="compact"
							class="mb-1" 
							variant="outlined"
							rounded="xl"
							divided
							color="primary"
							style="height: 28px;"
						>
							<v-btn value="and" size="small" class="text-caption">AND</v-btn>
							<v-btn value="or" size="small" class="text-caption">OR</v-btn>
						</v-btn-toggle>

					</v-col>

					<v-col cols="12" md="3">
						<v-text-field
							v-model="search"
							autofocus
							density="compact"
							variant="outlined"
							label="Search"
							hide-details
							spellcheck="false"
						>
							<!-- Custom Clear/Search Icons inside the input -->
							<template v-slot:append-inner>
								<v-fade-transition>
									<v-btn
										v-if="isAnySiteFilterActive"
										icon="mdi-filter-off"
										size="x-small"
										variant="text"
										color="error"
										class="mr-1"
										@click="clearSiteFilters()"
										title="Clear all filters"
									></v-btn>
								</v-fade-transition>
								<v-icon color="medium-emphasis">mdi-magnify</v-icon>
							</template>
						</v-text-field>
					</v-col>
					</v-row>
					<!-- Version and Status Filters -->
					<v-card color="transparent" class="pt-4" flat v-if="site_filter_version || site_filter_status">
					<v-row>
						<v-col cols="6" class="py-1">
							<template v-for="(primaryFilter, index) in combinedAppliedFilters" :key="primaryFilter.name + '-version'">
								<v-autocomplete
									ref="versionFilterRefs"
									v-if="primaryFilter && getVersionsForFilter(primaryFilter.name).length > 0"
									v-model="primaryFilter.selected_versions"
									@update:model-value="closeVersionFilter(index)"
									:items="getVersionsForFilter(primaryFilter.name)"
									:label="'Select Version for ' + primaryFilter.title"
									class="mb-2"
									item-title="name"
									return-object
									chips
									multiple
									hide-details
									hide-selected
									closable-chips
									density="compact"
									variant="outlined"
								>
									 <template v-slot:item="{ item, props }">
										<v-list-item v-bind="props" :title="item.raw.name">
											<template v-slot:append>
												<v-chip size="x-small" label class="ml-2">{{ item.raw.count }} sites</v-chip>
											</template>
										</v-list-item>
									</template>
									<template v-slot:append v-if="primaryFilter.selected_versions?.length > 1">
										<v-btn-toggle
											v-model="filter_version_logic"
											mandatory
											density="compact"
											variant="text"
											divided
										>
											<v-btn value="and" size="x-small">AND</v-btn>
											<v-btn value="or" size="x-small">OR</v-btn>
										</v-btn-toggle>
									</template>
								</v-autocomplete>
							</template>
						</v-col>

						<v-col cols="6" class="py-1">
							<template v-for="(primaryFilter, index) in combinedAppliedFilters" :key="primaryFilter.name + '-status'">
								<v-autocomplete
									ref="statusFilterRefs"
									v-if="primaryFilter && getStatusesForFilter(primaryFilter.name).length > 0"
									v-model="primaryFilter.selected_statuses"
									@update:model-value="closeStatusFilter(index)"
									:items="getStatusesForFilter(primaryFilter.name)"
									:label="'Select Status for ' + primaryFilter.title"
									class="mb-2"
									item-title="name"
									return-object
									chips
									multiple
									hide-details
									hide-selected
									closable-chips
									density="compact"
									variant="outlined"
								>
									<template v-slot:item="{ item, props }">
										<v-list-item v-bind="props" :title="item.raw.name">
											<template v-slot:append>
												<v-chip size="x-small" label class="ml-2">{{ item.raw.count }} sites</v-chip>
											</template>
										</v-list-item>
									</template>
									<template v-slot:append v-if="primaryFilter.selected_statuses?.length > 1">
										<v-btn-toggle
											v-model="filter_status_logic"
											mandatory
											density="compact"
											variant="text"
											divided
										>
											<v-btn value="and" size="x-small">AND</v-btn>
											<v-btn value="or" size="x-small">OR</v-btn>
										</v-btn-toggle>
									</template>
								</v-autocomplete>
							</template>
						</v-col>
					</v-row>
				</v-card>
				</v-card-text>
				<v-sheet v-show="dialog_site.step == 1 && sites.length > 0" class="px-4 pb-4" color="transparent">
					<v-data-iterator v-if="toggle_site === 'cards'" :items="filteredSites" :items-per-page="100" :search="search">
						<template v-slot:default="{ items }">
							<v-row dense>
								<v-col cols="12" v-for="item in items" :key="item.raw.site_id">
									<v-hover v-slot="{ isHovering, props }">
										<v-card 
											v-bind="props"
											class="rounded-lg transition-swing mb-2"
											elevation="0"
											border="thin"
											color="surface"
											:style="isHovering ? 'border-color: rgb(var(--v-border-color), 0.3) !important;' : 'border-color: rgba(var(--v-border-color), 0.1) !important;'"
										>
											<a :href="`${configurations.path}sites/${item.raw.site_id}`" class="px-4 py-1 d-flex align-center border-b bg-accent cursor-pointer text-decoration-none text-high-emphasis" @click.prevent="goToPath(`/sites/${item.raw.site_id}`)">
												<v-icon icon="mdi-web" size="small" class="mr-2 opacity-60" color="primary"></v-icon>
												<h3 class="text-subtitle-2 font-weight-black">
													{{ item.raw.name }}
												</h3>
												<v-spacer></v-spacer>
											</a>

											<template v-for="(env, index) in getVisibleEnvironments(item.raw)" :key="env.environment_id">
												<div class="d-flex flex-wrap flex-sm-nowrap align-center pa-2 px-4">
													<div 
														class="flex-shrink-0 mr-5 position-relative cursor-pointer" 
														@click="openEnvironmentTool(item.raw, env, '')"
														title="Open Site Info"
													>
														<v-img
															:src="`${remote_upload_uri}${item.raw.site}_${item.raw.site_id}/${env.environment.toLowerCase()}/screenshots/${env.screenshot_base}_thumb-800.jpg`"
															width="150"
															aspect-ratio="1.6"
															cover
															class="rounded-lg border-thin bg-grey-lighten-4 elevation-1"
															v-if="env.screenshot_base"
															lazy-src="/wp-content/plugins/captaincore-manager/public/dummy.webp"
														>
															<template v-slot:placeholder>
																<div class="d-flex align-center justify-center fill-height bg-grey-lighten-4">
																	<v-progress-circular color="grey-lighten-1" indeterminate size="16"></v-progress-circular>
																</div>
															</template>
															<template v-slot:error>
																<div class="d-flex align-center justify-center fill-height bg-surface-variant">
																	<v-icon size="small" class="text-medium-emphasis">mdi-monitor-shimmer</v-icon>
																</div>
															</template>
														</v-img>
														
														<v-sheet 
																v-else 
																width="150" 
																height="94" 
																color="surface-variant" 
																class="rounded-lg d-flex align-center justify-center border-thin"
															>
																<v-icon size="large" class="text-medium-emphasis">mdi-monitor-shimmer</v-icon>
															</v-sheet>
														</div>

													<div class="flex-grow-1 pr-4" style="min-width: 200px;">
														<div class="d-flex align-center mb-0">
															<v-chip size="x-small" label class="mr-2 font-weight-black" :color="env.environment == 'Production' ? 'green-darken-1' : 'brown-darken-1'">
																{{ env.environment.toUpperCase() }}
															</v-chip>
															<v-chip size="x-small" variant="tonal" class="text-caption font-weight-bold" :color="parseFloat(env.core) < 6.0 ? 'warning' : 'default'" label>
																WP {{ env.core }}
															</v-chip>
														</div>

														<div class="mb-1">
															<a 
																v-if="env.home_url"
																:href="env.home_url" 
																target="_blank" 
																class="text-caption text-medium-emphasis text-decoration-none hover-primary d-inline-flex align-center"
															>
																{{ env.home_url }} 
																<v-icon size="12" class="ml-1 opacity-70" icon="mdi-open-in-new"></v-icon>
															</a>
															<span v-else class="text-caption text-disabled font-italic">No URL detected</span>
														</div>

														<div class="d-flex align-center text-caption text-medium-emphasis gap-6" style="line-height: 1em;">
														<div class="d-flex align-center mr-6" title="Monthly Visits">
															<v-avatar color="surface-variant" size="28" class="mr-3">
																<v-icon size="16">mdi-chart-bar</v-icon>
															</v-avatar>
															<div>
																<div class="font-weight-bold text-high-emphasis text-caption">{{ formatLargeNumbers(env.visits) }}</div>
																<div class="text-xs">Visits</div>
															</div>
														</div>

														<div class="d-flex align-center mr-6" title="Storage Usage" style="line-height: 1em;">
															<v-avatar color="surface-variant" size="28" class="mr-3">
																<v-icon size="16">mdi-database-outline</v-icon>
															</v-avatar>
															<div>
																<div class="font-weight-bold text-high-emphasis text-caption">{{ formatGBs(env.storage) }} <span class="text-caption font-weight-regular">GB</span></div>
																<div class="text-xs">Storage</div>
															</div>
														</div>
														
														<div v-if="item.raw.subsites" class="d-flex align-center" title="Subsites">
															<v-avatar color="surface-variant" size="28" class="mr-3">
																<v-icon size="16">mdi-file-tree</v-icon>
															</v-avatar>
															<div>
																<div class="font-weight-bold text-high-emphasis text-body-2">{{ item.raw.subsites }}</div>
																<div class="text-xs">Sites</div>
															</div>
														</div>
													</div>
													</div>

													<div class="d-flex align-center justify-end flex-shrink-0 mt-3 mt-sm-0 gap-2">
														<v-btn 
															:href="`${configurations.path}sites/${item.raw.site_id}`"
															@click.prevent="openEnvironmentTool(item.raw, env, '')"
															color="primary"
															variant="flat"
															class="font-weight-bold text-capitalize mr-2"
															rounded="lg"
															prepend-icon="mdi-cog"
															elevation="0"
														>
															Manage Site
														</v-btn>
														<v-btn 
															variant="tonal" 
															class="text-none font-weight-bold" 
															rounded="lg" 
															append-icon="mdi-wordpress"
															:loading="env.isLoggingIn"
															@click="magicLoginSite(item.raw.site_id, null, env)"
														>
															WP Login
														</v-btn>

														<v-menu location="bottom end">
															<template v-slot:activator="{ props }">
																<v-btn icon="mdi-dots-vertical" variant="text" color="medium-emphasis" v-bind="props" density="comfortable"></v-btn>
															</template>
															<v-list density="compact" width="240" class="rounded-lg elevation-4 py-2">
																<v-list-item 
																	@click="openEnvironmentTool(item.raw, env, 'backups')" 
																	prepend-icon="mdi-cloud-upload-outline" 
																	title="Backups">
																</v-list-item>
																<v-list-item 
																	@click="openEnvironmentTool(item.raw, env, 'updates')" 
																	prepend-icon="mdi-history" 
																	title="Update Logs">
																</v-list-item>	
																<v-list-item 
																	@click="openEnvironmentTool(item.raw, env, 'quicksaves')" 
																	prepend-icon="mdi-file-restore" 
																	title="Version History">
																</v-list-item>
																<v-list-item 
																	@click="openEnvironmentTool(item.raw, env, 'visual-captures')" 
																	prepend-icon="mdi-camera-burst" 
																	title="Visual Captures">
																</v-list-item>

																<v-divider class="my-2"></v-divider>
																
																<v-list-item 
																	@click="runScript( item.raw.site_id, env.environment_id )" 
																	prepend-icon="mdi-console-line" 
																	title="Run Script">
																</v-list-item>
															</v-list>
														</v-menu>
													</div>
												</div>
												<v-divider v-if="index < getVisibleEnvironments(item.raw).length - 1" class="mx-4 opacity-30"></v-divider>
											</template>
										</v-card>
									</v-hover>
								</v-col>
							</v-row>
						</template>
					</v-data-iterator>

					<v-data-table
						v-if="toggle_site === 'table' || toggle_site === true"
						v-model="sites_selected"
						:headers="[
							{ title: '', key: 'thumbnail', sortable: false, width: 50 },
							{ title: 'Name', key: 'name', align: 'left', sortable: true },
							{ title: 'Subsites', key: 'subsites', sortable: true, width: 104 },
							{ title: 'WordPress', key: 'current_env.core', sortable: true, width: 114 },
							{ title: 'Visits', key: 'visits', sortable: true, width: 98 },
							{ title: 'Storage', key: 'storage', sortable: true, width: 90 },
							{ title: 'Provider', key: 'provider', sortable: true, width: 130 }
						]"
						:items="flattenedSiteEnvironments"
						item-value="unique_key"
						ref="site_datatable"
						:items-per-page="100"
						:items-per-page-options="[
							{ value: 100, title: '100' },
							{ value: 250, title: '250' },
							{ value: 500, title: '500' },
							{ value: -1, title: 'All' }
						]"
						@click:row="(event, { item }) => openEnvironmentTool(item, item.current_env, '')"
						:loading="sites_loading"
						hover
					>
						<template v-slot:no-data>
							<div v-if="sites_loading" class="d-flex justify-center align-center py-10">
								<v-progress-circular indeterminate color="primary"></v-progress-circular>
							</div>
							<div v-else>No sites found.</div>
						</template>

						<template v-slot:item.thumbnail="{ item }">
							<v-img
								:src="`${remote_upload_uri}${item.site}_${item.site_id}/${item.current_env.environment.toLowerCase()}/screenshots/${item.current_env.screenshot_base}_thumb-100.jpg`"
								class="elevation-1 my-1 rounded"
								width="50"
								aspect-ratio="1.6"
								v-if="item.current_env.screenshot_base"
								lazy-src="/wp-content/plugins/captaincore-manager/public/dummy.webp"
								cover
							></v-img>
							<v-sheet
								v-else
								width="50"
								height="31"
								color="surface-variant"
								class="rounded d-flex align-center justify-center border-thin my-1"
							>
								<v-icon size="x-small" class="text-medium-emphasis">mdi-monitor-shimmer</v-icon>
							</v-sheet>
						</template>
						<template v-slot:item.name="{ item }">
							<div class="d-flex align-center">
								<span class="font-weight-bold mr-2">{{ item.name }}</span>
								<v-chip 
									size="x-small" 
									label 
									class="font-weight-black" 
									:color="item.current_env.environment == 'Production' ? 'green-darken-1' : 'brown-darken-1'"
								>
									{{ item.current_env.environment.toUpperCase() }}
								</v-chip>
							</div>
							<div class="text-caption text-medium-emphasis" v-if="item.current_env.home_url">
								{{ item.current_env.home_url }}
							</div>
						</template>
						<template v-slot:item.subsites="{ item }">
							{{ item.subsites }}<span v-show="item.subsites"> sites</span>
						</template>
						<template v-slot:item.current_env.core="{ item }">
							{{ item.current_env.core }}
						</template>
						<template v-slot:item.visits="{ item }">
							{{ formatLargeNumbers(item.current_env.visits) }}
						</template>
						<template v-slot:item.storage="{ item }">
							{{ formatGBs(item.current_env.storage) }}GB
						</template>
						<template v-slot:item.provider="{ item }">
							{{ formatProvider(item.provider) }}
							<v-icon icon="mdi-cloud" color="secondary" class="ml-1 mr-0" v-show="item.provider_id && item.provider_id != 1" title="Maintenance only"></v-icon>
						</template>
					</v-data-table>

					<v-data-iterator
						v-if="toggle_site === 'grid' || toggle_site === false"
						:items="flattenedSiteEnvironments"
						:items-per-page="100"
						:search="search"
					>
						<template v-slot:no-data>
							<div v-if="sites_loading" class="d-flex justify-center align-center py-10">
								<v-progress-circular indeterminate color="primary"></v-progress-circular>
							</div>
							<div v-else class="text-center py-10 text-medium-emphasis">No sites found.</div>
						</template>
						<template v-slot:default="{ items }">
							<v-row>
								<v-col v-for="item in items" :key="item.raw.unique_key" cols="12" sm="6" md="4" lg="3">
									<v-card @click="openEnvironmentTool(item.raw, item.raw.current_env, '')" border="thin">
										<v-hover v-slot="{ isHovering, props }">
											<v-img
												v-bind="props"
												:src="`${remote_upload_uri}${item.raw.site}_${item.raw.site_id}/${item.raw.current_env.environment.toLowerCase()}/screenshots/${item.raw.current_env.screenshot_base}_thumb-800.jpg`"
												:aspect-ratio="16/10"
												cover
												v-if="item.raw.current_env.screenshot_base"
												lazy-src="/wp-content/plugins/captaincore-manager/public/dummy.webp"
											>
												<v-fade-transition>
													<div v-if="!isHovering" style="background-image: linear-gradient(rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.8)); height: 100%;" class="d-flex align-end justify-space-between pa-2">
														<div class="body-1 text-white font-weight-bold text-truncate">{{ item.raw.name }}</div>
														<v-chip 
															size="x-small" 
															label 
															class="font-weight-black mb-1 ml-2" 
															:color="item.raw.current_env.environment == 'Production' ? 'green-darken-1' : 'brown-darken-1'"
														>
															{{ item.raw.current_env.environment.toUpperCase() }}
														</v-chip>
													</div>
												</v-fade-transition>
												<template v-slot:placeholder>
													<v-row class="fill-height ma-0" align="center" justify="center">
														<v-progress-circular indeterminate color="grey-lighten-5"></v-progress-circular>
													</v-row>
												</template>
											</v-img>
											<v-responsive 
												v-else 
												v-bind="props" 
												:aspect-ratio="16/10"
											>
												<v-sheet 
													color="surface-variant" 
													class="d-flex align-center justify-center fill-height" 
													height="100%"
												>
													<div class="text-center" style="width: 100%;">
														<v-icon size="large" class="text-medium-emphasis mb-2">mdi-monitor-shimmer</v-icon>
														<div class="px-2">
															<div class="body-1 font-weight-bold text-truncate">{{ item.raw.name }}</div>
															<v-chip 
																size="x-small" 
																label 
																class="font-weight-black mt-1" 
																:color="item.raw.current_env.environment == 'Production' ? 'green-darken-1' : 'brown-darken-1'"
															>
																{{ item.raw.current_env.environment.toUpperCase() }}
															</v-chip>
														</div>
													</div>
												</v-sheet>
											</v-responsive>
										</v-hover>
									</v-card>
								</v-col>
							</v-row>
						</template>
						<template v-slot:footer="{ page, pageCount, prevPage, nextPage }">
							<div class="d-flex align-center justify-center pa-4">
								<v-btn icon="mdi-chevron-left" variant="text" @click="prevPage" :disabled="page === 1"></v-btn>
								<span class="mx-2 text-caption">Page {{ page }} of {{ pageCount }}</span>
								<v-btn icon="mdi-chevron-right" variant="text" @click="nextPage" :disabled="page === pageCount"></v-btn>
							</div>
						</template>
					</v-data-iterator>
				</v-sheet>
			</v-sheet>
			<v-sheet v-show="dialog_site.step == 2" class="site" color="transparent">
			<v-card v-show="dialog_site.site.removed" elevation="0" rounded="xl">
				<v-toolbar elevation="0" color="transparent">
					<v-img :src=`${remote_upload_uri}${dialog_site.site.site}_${dialog_site.site.site_id}/production/screenshots/${dialog_site.site.screenshot_base}_thumb-100.jpg` class="elevation-1 mr-3 ml-5" max-width="50" v-show="dialog_site.site.screenshot_base"></v-img>
					<v-toolbar-title>{{ dialog_site.site.name }}</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card-text>
				<v-row>
					<v-col>
						<div>This site has been marked for removal and will be removed within 24 hours. If that was not your intentions then:</div>
						<v-btn color="primary" class="mt-4" @click="cancelSiteRemoved()">cancel removal request</v-btn>
					</v-col>
				</v-row>
				</v-card-text>
			</v-card>
			<v-card v-show="! dialog_site.site.removed" flat rounded="xl">
				<v-toolbar flat color="transparent">
					<v-img
						:key="dialog_site.site.site_id"
						:src="currentSiteThumbnail" 
						class="elevation-1 ml-5 rounded flex-grow-0" 
						width="50" 
						max-width="50"
						aspect-ratio="1.6"
						cover
					>
						<template v-slot:placeholder>
							<div class="d-flex align-center justify-center fill-height bg-grey-lighten-4">
								<v-progress-circular 
									indeterminate 
									color="grey-lighten-1" 
									size="16"
									width="2"
								></v-progress-circular>
							</div>
						</template>
						<template v-slot:error>
							<v-sheet 
								width="50" 
								max-width="50"
								height="31" 
								color="surface-variant" 
								class="elevation-1 rounded d-flex align-center justify-center border-thin flex-grow-0"
							>
								<v-icon size="x-small" class="text-medium-emphasis">mdi-monitor-shimmer</v-icon>
							</v-sheet>
						</template>
					</v-img>

					<v-toolbar-title>
						<v-autocomplete
							v-model="selected_site"
							ref="autocompleteRef"
							:items="sites"
							item-title="name"
							item-value="site_id"
							return-object
							@update:model-value="switchSite"
							density="compact"
							variant="outlined"
							spellcheck="false"
							style="max-width: 300px;"
							flat
							hide-details
						>
						</v-autocomplete>
					</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-container class="pt-0">
				<v-toolbar color="primary" flat density="compact" rounded="lg">
				<v-tabs v-model="dialog_site.site.tabs" density="compact" hide-slider>
					<v-tab value="tab-Site-Management" :href="`${configurations.path}sites/${dialog_site.site.site_id}`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}`)">
						Site Management <v-icon icon="mdi-cog" class="ml-1"></v-icon>
					</v-tab>
					<v-tab value="tab-Modules" :href="`${configurations.path}sites/${dialog_site.site.site_id}`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}`)" v-show="role == 'administrator'">
						Modules <v-icon size="24" icon="mdi-toggle-switch-outline" class="ml-1"></v-icon>
					</v-tab>
					<v-tab value="tab-Timeline" :href="`${configurations.path}sites/${dialog_site.site.site_id}`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}`)" ripple @click="fetchTimeline( dialog_site.site.site_id )">
						Timeline <v-icon size="24" icon="mdi-timeline-text-outline" class="ml-1"></v-icon>
					</v-tab>
				</v-tabs>
				<v-spacer></v-spacer>
				<v-toolbar-items>
					<v-tooltip text="New Log Entry" location="top">
					<template v-slot:activator="{ props }">
						<v-btn
						v-bind="props"
						v-show="role == 'administrator' || role == 'owner'"
						variant="text"
						icon="mdi-note-check-outline"
						@click="showLogEntry(dialog_site.site.site_id)"
						></v-btn>
					</template>
					</v-tooltip>
					<v-tooltip text="Share access" location="top">
					<template v-slot:activator="{ props }">
						<v-btn
						v-bind="props"
						variant="text"
						icon="mdi-share-variant-outline"
						@click="openShareDialog(dialog_site.site)"
						></v-btn>
					</template>
					</v-tooltip>
					<v-btn variant="text" @click="magicLoginSite(dialog_site.site.site_id, null, dialog_site.environment_selected)" :loading="dialog_site.environment_selected.isLoggingIn">Login to WordPress <v-icon class="ml-1"s>mdi-open-in-new</v-icon></v-btn>
				</v-toolbar-items>
				</v-toolbar>
				<v-window v-model="dialog_site.site.tabs">
					<v-window-item value="tab-Site-Management" :transition="false" :reverse-transition="false">
						<div>
						<v-row class="mb-2">
						<v-col class="pt-7" style="max-width: 280px;">
							<v-select
								v-model="dialog_site.environment_selected"
								:items="dialog_site.site.environments"
								item-title="environment_label"
								item-value="id" 
								return-object
								@update:model-value="triggerEnvironmentUpdate"
								label="Environment"
								variant="outlined"
								density="compact"
							>
							</v-select>
						</v-col>
						<div class="mt-5">
							<v-tooltip location="bottom">
								<template v-slot:activator="{ props }">
									<v-btn size="large" variant="text" @click="syncSite()" style="position: relative; left: -16px;" v-bind="props" icon="mdi-sync" color="grey"></v-btn>
								</template>
								<span>Manual sync website details. Last sync {{ timeago( dialog_site.site.updated_at ) }}.</span>
							</v-tooltip>
						</div>
						<v-col>
								<v-tabs v-model="dialog_site.site.tabs_management" align-tabs="end" show-arrows class="pr-3" density="compact" color="primary" stacked>
									<v-tab value="tab-Info" style="min-width: 50px;padding: 0px 10px;" :href="`${configurations.path}sites/${dialog_site.site.site_id}`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}`)"><v-icon>mdi-text-box-multiple</v-icon> Info</v-tab>
									<v-tab value="tab-Stats" style="min-width: 50px;padding: 0px 10px;" :href="`${configurations.path}sites/${dialog_site.site.site_id}/stats`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/stats`)"><v-icon start>mdi-chart-bar</v-icon> Stats</v-tab>
									<v-tab value="tab-Logs" style="min-width: 50px;padding: 0px 10px;" :href="`${configurations.path}sites/${dialog_site.site.site_id}/logs`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/logs`)"><v-icon start>mdi-file-document-multiple</v-icon> Logs</v-tab>
									<v-tab value="tab-Addons" style="min-width: 50px;padding: 0px 10px;" :href="`${configurations.path}sites/${dialog_site.site.site_id}/addons`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/addons`)" v-if="dialog_site.environment_selected.token !== 'basic'"><v-icon start>mdi-power-plug</v-icon> Addons</v-tab>
									<v-tab value="tab-Users" style="min-width: 50px;padding: 0px 10px;" :href="`${configurations.path}sites/${dialog_site.site.site_id}/users`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/users`)" v-if="dialog_site.environment_selected.token !== 'basic'"><v-icon start>mdi-account-multiple</v-icon> Users</v-tab>
									<v-tab value="tab-Updates" style="min-width: 50px;padding: 0px 10px;" :href="`${configurations.path}sites/${dialog_site.site.site_id}/updates`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/updates`)" v-if="dialog_site.environment_selected.token !== 'basic'"><v-icon start>mdi-book-open</v-icon> Updates</v-tab>
									<v-tab value="tab-Scripts" style="min-width: 50px;padding: 0px 10px;" :href="`${configurations.path}sites/${dialog_site.site.site_id}/scripts`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/scripts`)"><v-icon start>mdi-code-tags</v-icon> Scripts</v-tab>
									<v-tab value="tab-Backups" style="min-width: 50px;padding: 0px 10px;" :href="`${configurations.path}sites/${dialog_site.site.site_id}/backup-overview`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/backup-overview`)"><v-icon start>mdi-update</v-icon> Backups</v-tab>
								</v-tabs>
							</v-col>
							</v-row>
						</div>
				<v-dialog v-model="dialog_site.environment_selected.view_server_logs" fullscreen scrollable>
					<v-card flat rounded="0">
						<v-toolbar color="primary" class="shrink">
							<v-btn icon="mdi-close" @click="dialog_site.environment_selected.view_server_logs = false"></v-btn>
							<v-toolbar-title>Server logs for {{ dialog_site.environment_selected.home_url }}</v-toolbar-title>
						</v-toolbar>
						<v-card-text class="mt-5 pb-5">
							<v-progress-circular 
								indeterminate 
								color="primary" 
								class="mt-7 mb-7" 
								size="24" 
								v-if="typeof dialog_site.environment_selected.server_logs != 'undefined' && dialog_site.environment_selected.server_logs.files == ''">
							</v-progress-circular>
							
							<v-row v-if="typeof dialog_site.environment_selected.server_logs != 'undefined' && dialog_site.environment_selected.server_logs.files != ''" >
								<v-col>
									<v-autocomplete 
										v-model="dialog_site.environment_selected.server_log_selected" 
										:items="dialog_site.environment_selected.server_logs.files" 
										item-title="name" 
										item-value="path" 
										variant="underlined" 
										label="Select log" 
										@update:model-value="fetchLogs()" 
										spellcheck="false">
									</v-autocomplete>
								</v-col>
								<v-col class="shrink" style="min-width:200px;max-width:200px">
									<v-select 
										v-model="dialog_site.environment_selected.server_log_limit" 
										:items="['100','1000','5000','10000']" 
										variant="underlined" 
										label="Log limit" 
										@update:model-value="fetchLogs()">
									</v-select>
								</v-col>
							</v-row>

							<v-progress-circular 
								indeterminate 
								color="primary" 
								class="mt-2" 
								size="24" 
								v-show="dialog_site.environment_selected.loading_server_logs">
							</v-progress-circular>
							
							<pre style="font-size: 13px;" class="overflow-auto" v-show="dialog_site.environment_selected.server_log_response != ''"><code class="language-log" v-html="dialog_site.environment_selected.server_log_response"></code></pre>
						</v-card-text>
					</v-card>
				</v-dialog>
        		<v-window v-model="dialog_site.site.tabs_management" v-if="dialog_site.loading != true">
					<v-window-item :key="1" value="tab-Info" :transition="false" :reverse-transition="false">
						<v-toolbar density="compact" flat color="transparent">
							<v-toolbar-title>Info</v-toolbar-title>
						</v-toolbar>
						<v-card v-if="currentEnvironmentAction" flat class="d-flex align-center justify-center text-center pa-10" style="min-height: 400px; background: transparent;">
							<div>
								<div class="mb-6 position-relative d-inline-block">
									<v-progress-circular
										indeterminate
										color="primary"
										size="80"
										width="6"
									></v-progress-circular>
									<v-icon
										color="primary"
										size="32"
										class="position-absolute"
										style="top: 50%; left: 50%; transform: translate(-50%, -50%);"
									>
										mdi-source-branch
									</v-icon>
								</div>
								
								<h2 class="text-h5 font-weight-bold mb-3">
									Pushing to {{ dialog_site.environment_selected.environment }}...
								</h2>
								
								<p class="text-body-1 text-medium-emphasis mb-6" style="max-width: 600px; margin: 0 auto;">
									{{ currentEnvironmentAction.message || "We're busy pushing content to this environment." }}
								</p>

								<v-alert type="info" variant="tonal" border="start" class="text-left mx-auto" style="max-width: 500px;">
									This environment is currently locked for changes. A backup is automatically being made which you can use to roll back if necessary. We'll notify you as soon as the process is complete.
								</v-alert>
							</div>
						</v-card>
						<v-card flat v-else>
							<v-container fluid>
							<v-alert type="info" variant="text" v-show="dialog_site.environment_selected.token == 'basic'">This site doesn't appear to be WordPress. Backups will still work however other management functions have been disabled.</v-alert>
							<v-row>
							<v-col cols="12" md="6" class="py-2">
							<div class="block mt-6 text-center">
								<a :href="`${configurations.path}sites/${dialog_site.site.site_id}/visual-captures`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/visual-captures`)" class="text-decoration-none">
								
								<!-- Image exists: Try to load it -->
								<v-img 
									v-if="dialog_site.environment_selected.screenshot_base" 
									:src="`${remote_upload_uri}${dialog_site.site.site}_${dialog_site.site.site_id}/${dialog_site.environment_selected.environment.toLowerCase()}/screenshots/${dialog_site.environment_selected.screenshot_base}_thumb-800.jpg`" 
									max-width="400" 
									aspect-ratio="1.6" 
									class="elevation-5 mx-auto rounded-lg" 
									cover 
									lazy-src="/wp-content/plugins/captaincore-manager/public/dummy.webp"
								>
									<template v-slot:placeholder>
										<div class="d-flex align-center justify-center fill-height bg-grey-lighten-4">
											<v-progress-circular indeterminate color="grey-lighten-1"></v-progress-circular>
										</div>
									</template>
									<!-- Logic for 404/Load Error -->
									<template v-slot:error>
										<div class="d-flex align-center justify-center fill-height bg-surface-variant">
											<v-icon size="64" class="text-medium-emphasis">mdi-monitor-shimmer</v-icon>
										</div>
									</template>
								</v-img>

								<!-- Logic for no screenshot data -->
								<v-sheet 
									v-else 
									max-width="400" 
									height="250" 
									color="surface-variant" 
									class="elevation-5 mx-auto rounded-lg d-flex align-center justify-center border-thin"
								>
									<v-icon size="64" class="text-medium-emphasis">mdi-monitor-shimmer</v-icon>
								</v-sheet>
								</a>
							</div>
							<v-list density="compact" class="mt-6 mx-auto" style="max-width: 350px; background: transparent; padding: 0px;">
								<v-list-item :href="dialog_site.environment_selected.link" target="_blank" density="compact" title="Link" :subtitle="dialog_site.environment_selected.link" append-icon="mdi-open-in-new" link></v-list-item>
								<v-list-item density="compact" title="Created" :subtitle="pretty_timestamp(dialog_site.environment_selected.created_at)" append-icon="mdi-calendar"></v-list-item>
								<v-list-item v-if="dialog_site.environment_selected.token !== 'basic'" @click="copyText(dialog_site.environment_selected.core)" density="compact" title="WordPress Version" :subtitle="dialog_site.environment_selected.core" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(formatSize(dialog_site.environment_selected.storage))" density="compact" title="Storage" :subtitle="formatSize(dialog_site.environment_selected.storage)" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.php_memory)" density="compact" title="Memory Limit" :subtitle="dialog_site.environment_selected.php_memory" append-icon="mdi-content-copy" v-show="dialog_site.environment_selected.php_memory"></v-list-item>
								<v-list-item @click="showCaptures(dialog_site.site.site_id)" density="compact" title="Visual Captures" :subtitle="dialog_site.environment_selected.captures?.toString()" append-icon="mdi-image"></v-list-item>
								<v-list-item v-if="dialog_site.environment_selected.subsite_count" @click="copyText(`${dialog_site.environment_selected.subsite_count} subsites`)" density="compact" title="Multisite" :subtitle="`${dialog_site.environment_selected.subsite_count} subsites`" append-icon="mdi-content-copy"></v-list-item>
							</v-list>
							</v-col>

							<v-col cols="12" md="6" class="keys py-2">
							<v-list density="compact" class="mx-auto" style="max-width: 350px; background: transparent; padding: 0px;">
								<v-list-item @click="copySFTP(dialog_site.environment_selected)" density="compact" title="SFTP Info" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item v-if="dialog_site.environment_selected.database" @click="copyDatabase(dialog_site.environment_selected)" density="compact" title="Database Info" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.address)" density="compact" title="Address" :subtitle="dialog_site.environment_selected.address" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.username)" density="compact" title="Username" :subtitle="dialog_site.environment_selected.username" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.password)" density="compact" title="Password" subtitle="##########" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.protocol)" density="compact" title="Protocol" :subtitle="dialog_site.environment_selected.protocol" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.port)" density="compact" title="Port" :subtitle="dialog_site.environment_selected.port?.toString()" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.home_directory)" density="compact" title="Home directory" :subtitle="dialog_site.environment_selected.home_directory" append-icon="mdi-content-copy"></v-list-item>

								<div v-if="dialog_site.environment_selected.database_name">
								<v-list-item v-if="dialog_site.environment_selected.database && dialog_site.site.provider !== 'rocketdotnet' && dialog_site.site.provider !== 'kinsta'" :href="dialog_site.environment_selected.database" target="_blank" density="compact" title="Database" :subtitle="dialog_site.environment_selected.database" append-icon="mdi-open-in-new" link></v-list-item>
								<v-list-item v-if="dialog_site.site.provider === 'rocketdotnet' || dialog_site.site.provider === 'kinsta'" @click="fetchPHPmyadmin()" density="compact" title="Database" subtitle="PHPmyadmin" append-icon="mdi-open-in-new"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.database_name)" density="compact" title="Database Name" :subtitle="dialog_site.environment_selected.database_name" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.database_username)" density="compact" title="Database Username" :subtitle="dialog_site.environment_selected.database_username" append-icon="mdi-content-copy"></v-list-item>
								<v-list-item @click="copyText(dialog_site.environment_selected.database_password)" density="compact" title="Database Password" subtitle="##########" append-icon="mdi-content-copy"></v-list-item>
								</div>

								<div v-if="dialog_site.environment_selected.ssh">
								<v-list-item @click="copyText(dialog_site.environment_selected.ssh)" density="compact" title="SSH Connection" :subtitle="dialog_site.environment_selected.ssh" append-icon="mdi-content-copy"></v-list-item>
								</div>
							</v-list>
							</v-col>
						</v-row>
						</v-container>
						<div v-show="dialog_site.site.shared_with && dialog_site.site.shared_with.length > 0">
						<v-list-subheader class="ml-4">Shared With
							<v-menu v-model="dialog_site.grant_access_menu" :close-on-content-click="false" :nudge-width="200" offset-x>
								<template v-slot:activator="{ props }">
									<v-btn size="small" variant="tonal" class="ml-2" v-bind="props" v-show="accounts && accounts.length > 1">Add <v-icon class="ml-2">mdi-account-multiple-plus</v-icon></v-btn>
								</template>
								<v-card min-width="300">
									<v-list>
										<v-list-item>
											<v-autocomplete ref="accountAutocomplete" label="Accounts" hide-details outlined small-chips :items="accounts.filter( account => !dialog_site.site.shared_with.find( shared => shared.account_id == account.account_id ) )" item-title="name" item-value="account_id" v-model="dialog_site.grant_access" style="max-width: 400px"></v-autocomplete>
										</v-list-item>
									</v-list>
									<v-divider></v-divider>
									<v-card-actions>
										<v-spacer></v-spacer>
										<v-btn color="primary" text @click="grantAccess(); dialog_site.grant_access_menu = false">
											Grant Access
										</v-btn>
									</v-card-actions>
								</v-card>
							</v-menu>
						</v-list-subheader>
						<v-container>
						<v-row density="compact" v-if="dialog_site.site.shared_with && dialog_site.site.shared_with.length > 0">
							<v-col v-for="account in dialog_site.site.shared_with" :key="account.account_id" cols="12" md="4">
							<v-card :href="`${configurations.path}accounts/${account.account_id}`" @click.prevent="goToPath( '/accounts/' + account.account_id )" density="compact" flat border="thin" rounded="xl">
								<v-card-title class="text-body-1 d-flex align-center">
									<span v-html="account.name" class="text-truncate overflow-hidden d-inline-block"></span>
									<v-spacer></v-spacer>
									<div class="d-flex align-center flex-shrink-0">
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }">
										<v-icon color="primary" v-bind="props" size="26" v-show="account.account_id == dialog_site.site.customer_id" class="ml-1">mdi-account-circle</v-icon>
										</template>
										<span>Customer</span>
									</v-tooltip>
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }">
										<v-icon color="primary" v-bind="props" size="26" v-show="account.account_id == dialog_site.site.account_id" class="ml-1">mdi-credit-card</v-icon>
										</template>
										<span>Billing Contact</span>
									</v-tooltip>
									</div>
								</v-card-title>
								<v-card-subtitle class="mb-3">Account #{{ account.account_id }}</v-card-subtitle>
							</v-card>
						</v-col>
						</v-row>
						</v-container>
					</div>
					<div v-if="dialog_site.site.domains && dialog_site.site.domains.length > 0">
					<v-container>
						<v-list-subheader>DNS zones</v-list-subheader>
						<v-row dense>
							<v-col v-for="domain in dialog_site.site.domains" :key="domain.domain_id" cols="12" md="4">
							<v-card :href=`${configurations.path}domains/${domain.domain_id}` @click.prevent="goToPath( '/domains/' + domain.domain_id )" density="compact" flat border="thin" rounded="xl">
								<v-card-title class="text-body-1">
									<span v-html="domain.name"></span>
								</v-card-title>
							</v-card>
						</v-col>
						</v-row>
					</v-container>
					</div>
					<div v-show="dialog_site.environment_selected.token != 'basic'">
					<v-card color="transparent" density="compact" flat subtitle="Site Options">
						<template v-slot:actions>
						<v-btn size="small" variant="tonal" @click="PushProductionToStaging( dialog_site.site.site_id )" prepend-icon="mdi-truck" v-show="dialog_site.site && dialog_site.site.provider && dialog_site.site.provider == 'kinsta'">
							Push Production to Staging
						</v-btn>
						<v-btn size="small" variant="tonal" @click="PushStagingToProduction( dialog_site.site.site_id )" v-show="dialog_site.site && dialog_site.site.provider && dialog_site.site.provider == 'kinsta' && typeof dialog_site.site.environments == 'object' && dialog_site.site.environments.length == 2">
							<template v-slot:prepend>
								<v-icon style="transform: scaleX(-1);">mdi-truck</v-icon>
							</template>
							Pull Staging to Production
						</v-btn>
						<v-btn
							size="small"
							variant="tonal"
							@click="showPushToOtherDialog()"
							prepend-icon="mdi-source-branch"
							v-show="dialog_site.site && dialog_site.site.provider && dialog_site.site.provider == 'kinsta'">
							Push to another...
						</v-btn>
						<v-dialog max-width="600">
							<template v-slot:activator="{ props }">
								<v-btn size="small" variant="tonal" color="error" v-bind="props" prepend-icon="mdi-delete"> Delete Site</v-btn>
							</template>
							<template v-slot:default="{ isActive }">
							<v-card>
								<v-toolbar color="primary">
									<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
									Are you sure you wish to delete this site?
									<v-spacer></v-spacer>
								</v-toolbar>
								<v-card-text>
								<p class="pt-3 text-body-1">Deleting this site will also delete all environments associated with it.</p>
								<v-checkbox 
									:model-value="true" 
									readonly 
									v-for="environment in dialog_site.site.environments" 
									:label="`${environment.environment} - ${environment.home_url}`" 
									color="primary" 
									hide-details
									density="compact"
								></v-checkbox>
								<v-btn color="primary" @click="markSiteRemoved(); isActive.value = false" class="mr-2 mt-2">Delete Site</v-btn>
								</v-card-text>
							</v-card>
							</template>
						</v-dialog>
						</template>
					</v-card>
					</div>
				</v-window-item>
				<v-window-item :key="100" value="tab-Stats" :transition="false" :reverse-transition="false">
					<v-card flat>
					<v-toolbar flat density="compact" color="transparent" class="mb-2">
						<v-toolbar-title>Stats</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-toolbar-items v-if="typeof dialog_new_site == 'object'" style="margin-right:-16px;" class="mt-2">
							<div class="px-1" v-show="dialog_site.environment_selected.fathom_analytics.length > 1">
								<v-autocomplete
									:items='dialog_site.environment_selected.fathom_analytics'
									item-title="domain"
									item-value="code"
									v-model="dialog_site.environment_selected.stats.fathom_id"
									label="Domain"
									@change="fetchStats"
								></v-autocomplete>
							</div>
							<div class="px-1" style="width:150px;">
								<v-select density="compact" variant="outlined" :items="['Hour', 'Day', 'Month', 'Year']" label="Date Grouping" v-model="stats.grouping" @update:model-value="fetchStats()"></v-select>
							</div>
							<div class="px-1" style="width:162px;">
							<v-menu
								v-model="stats.from_at_select"
								:close-on-content-click="false"
								transition="scale-transition"
								offset-y
								left
								down
								min-width="auto"
							>
								<template v-slot:activator="{ props }">
								<v-text-field v-model="stats.from_at" label="From" append-icon="mdi-calendar" v-bind="props" density="compact" variant="outlined"></v-text-field>
								</template>
								<v-date-picker :model-value="new Date(stats.from_at)" @update:model-value="handleDateChange($event, 'from_at')"></v-date-picker>
							</v-menu>
							</div>
							<div class="px-1" style="width:162px;">
							<v-menu
								v-model="stats.to_at_select"
								:close-on-content-click="false"
								transition="scale-transition"
								offset-y
								left
								down
								min-width="auto"
							>
								<template v-slot:activator="{ props }">
								<v-text-field
									v-model="stats.to_at"
									label="To"
									append-icon="mdi-calendar"
									v-bind="props"
									density="compact"
									variant="outlined"
								></v-text-field>
								</template>
								<v-date-picker :model-value="new Date(stats.to_at)" @update:model-value="handleDateChange($event, 'to_at')"></v-date-picker>
							</v-menu>
							</div>
                    		<v-btn variant="text" @click="configureFathom( dialog_site.site.site_id )" v-show="role == 'administrator'"><v-icon dark small>mdi-pencil</v-icon> Edit</v-btn>
						</v-toolbar-items>
					</v-toolbar>
						<div class="pa-3" v-if="typeof dialog_site.environment_selected.stats == 'string' && dialog_site.environment_selected.stats != 'Loading'">
							{{ dialog_site.environment_selected.stats }}
						</div>
						<v-row>
						<v-col>
						<v-card-text v-show="dialog_site.environment_selected.stats == 'Loading'">
							<span><v-progress-circular indeterminate color="primary" class="ma-2" size="24"></v-progress-circular></span>
						</v-card-text>
						<div v-for="e in dialog_site.site.environments" v-show="e.environment == dialog_site.environment_selected.environment">
							<div :id="`chart_` + dialog_site.site.site_id + `_` + e.environment" class="stat-chart"></div>
							<v-card flat v-if="dialog_site.environment_selected.stats && dialog_site.environment_selected.stats.summary">
							<v-card-title class="text-center pa-0 mb-10">
							<v-row>
							<v-col cols="6" sm="3">
								<span class="text-uppercase text-caption">Unique Visitors</span><br />
								<span class="text-h4 font-weight-thin text-uppercase">{{ formatk( dialog_site.environment_selected.stats.summary.visits ) }}</span>
							</v-col>
							<v-col cols="6" sm="3">
								<span class="text-uppercase text-caption">Pageviews</span><br />
								<span class="text-h4 font-weight-thin text-uppercase">{{ formatk( dialog_site.environment_selected.stats.summary.pageviews ) }}</span>
							</v-col>
							<v-col cols="6" sm="3">
								<span class="text-uppercase text-caption">Avg Time On Site</span><br />
								<span class="text-h4 font-weight-thin text-uppercase">{{ formatTime( dialog_site.environment_selected.stats.summary.avg_duration ) }}</span>
							</v-col>
							<v-col cols="6" sm="3">
								<span class="text-uppercase text-caption">Bounce Rate</span><br />
								<span class="text-h4 font-weight-thin text-uppercase">{{ formatPercentageFixed( dialog_site.environment_selected.stats.summary.bounce_rate ) }}</span>
							</v-col>
							</v-row>
							</v-card-title>
							</v-card>
						</div>
						</v-col>
						</v-row>
						<div v-if="dialog_site.environment_selected && dialog_site.environment_selected.stats.site" class="mb-10">
						<v-divider></v-divider>
						<v-tab>Sharing</v-tab>
							<v-row>
								<v-col>
								<v-card-text>
									Stats are powered by <a href="https://usefathom.com" target="_new">Fathom Analytics</a>. To view the stats dashboard directly, you can enable public or private sharing options.
									<v-chip-group mandatory active-class="primary-text" v-model="dialog_site.environment_selected.stats.site.sharing" @update:model-value="shareStats()">
										<v-chip value="none" filter>Off</v-chip>
										<v-chip value="private" filter @click="dialog_site.environment_selected.stats_password = 'changeme'">Private</v-chip>
										<v-chip value="public" filter>Public</v-chip>
									</v-chip-group>
								</v-card-text>
								</v-col>
								<v-col v-show="dialog_site.environment_selected.stats.site.sharing != 'none'">
								<v-list-item :href="`https://app.usefathom.com/share/${ dialog_site.environment_selected.stats.site.id.toLowerCase() }/${dialog_site.environment_selected.stats.site.name}`" target="_new" density="compact" lines="two">
									<template v-slot:title>Share URL</template>
									<template v-slot:subtitle>https://app.usefathom.com/share/{{ dialog_site.environment_selected.stats.site.id.toLowerCase() }}/{{ dialog_site.environment_selected.stats.site.name }}</template>
									<template v-slot:append>
									<v-icon>mdi-open-in-new</v-icon>
									</template>
								</v-list-item>
								<v-list-item v-show="dialog_site.environment_selected.stats.site.sharing == 'private'" class="mt-4" lines="two">
									<template v-slot:title>
									<v-text-field label="Change Share Password" v-model="dialog_site.environment_selected.stats_password" spellcheck="false" clearable autofocus></v-text-field>
									</template>
									<template v-slot:append>
									<v-btn @click="shareStats()">Save</v-btn>
									</template>
								</v-list-item>
								</v-col>
							</v-row>
						</div>
					</v-card>
				</v-window-item>
				<v-window-item :key="104" value="tab-Logs" :transition="false" :reverse-transition="false">
				<v-toolbar density="compact" color="transparent" flat>
					<v-toolbar-title>Logs</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-sheet>
				<v-card flat>
				<v-row class="pa-4">
					<v-col cols="12" md="4" class="px-2">
					<v-card class="mx-auto pb-2" max-width="344" variant="outlined" border="thin" hover @click="viewLogs(dialog_site.site.site_id)">
						<v-card-title>Server Logs</v-card-title>
						<v-card-subtitle><code>error.log</code> and <code>access.log</code></v-card-subtitle>
					</v-card>
					</v-col>
					<v-col cols="12" md="4" class="px-2" v-show="dialog_site.environment_selected.token != 'basic'">
					<v-card class="mx-auto pb-2" max-width="344" variant="outlined" border="thin" hover v-if="dialog_site.site.cleantalk">
						<v-card-title>Spam Logs</v-card-title>
						<v-card-subtitle>Logs from CleanTalk spam filter</v-card-subtitle>
					</v-card>
					</v-col>
				</v-row>
				</v-card>
				</v-sheet>
				</v-window-item>
				<v-window-item :key="3" value="tab-Addons" :transition="false" :reverse-transition="false">
				<v-card flat color="transparent">
				<v-toolbar density="compact" flat color="transparent">
					<v-toolbar-title>Addons <small>(Themes/Plugins)</small></v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
					<v-btn @click="bulkEdit(dialog_site.site.site_id, 'plugins')" v-if="dialog_site.environment_selected.plugins_selected.length != 0">Bulk Edit {{ dialog_site.environment_selected.plugins_selected.length }} plugins</v-btn>
					<v-btn @click="bulkEdit(dialog_site.site.site_id, 'themes')" v-if="dialog_site.environment_selected.themes_selected.length != 0">Bulk Edit {{ dialog_site.environment_selected.themes_selected.length }} themes</v-btn>
					<v-btn @click="addTheme(dialog_site.site.site_id)">Add Theme <v-icon size="small">mdi-plus</v-icon></v-btn>
					<v-btn @click="addPlugin(dialog_site.site.site_id)">Add Plugin <v-icon size="small">mdi-plus</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-title v-if="typeof dialog_site.environment_selected.themes == 'string'">
					<div>
					Updating themes...
					<v-progress-linear :indeterminate="true"></v-progress-linear>
					</div>
				</v-card-title>
				<div v-else>
					<v-list-subheader>Themes</v-list-subheader>
					<v-data-table v-model="dialog_site.environment_selected.themes_selected" :headers="header_themes" :items="dialog_site.environment_selected.themes" :loading="dialog_site.site.loading_themes" :items-per-page="-1" :items-per-page-options="[{'title':'All','value':-1}]" item-value="name" show-select hide-default-footer>
					<template v-slot:item.title="{ item }">
						<div v-html="item.title"></div>
					</template>
					<template v-slot:item.status="{ item }">
						<div v-if="item.status === 'inactive' || item.status === 'parent' || item.status === 'child'">
						<v-switch hide-details v-model="item.status" false-value="inactive" true-value="active" @update:model-value="activateTheme( item.name, dialog_site.site.site_id )"></v-switch>
						</div>
						<div v-else>
						{{ item.status }}
						</div>
					</template>
					<template v-slot:item.actions="{ item }">
						<v-btn variant="text" class="mx-0" @click="deleteTheme(item.name, dialog_site.site.site_id)">
						<v-icon color="pink">mdi-delete</v-icon>
						</v-btn>
					</template>
					</v-data-table>
				</div>
				<v-card-title v-if="typeof dialog_site.environment_selected.plugins == 'string'">
					<div>
					Updating plugins...
					<v-progress-linear :indeterminate="true"></v-progress-linear>
					</div>
				</v-card-title>
				<div v-else>
					<v-list-subheader>Plugins</v-list-subheader>
					<v-data-table :headers="header_plugins" :items="dialog_site.environment_selected.plugins.filter(plugin => plugin.status != 'must-use' && plugin.status != 'dropin')" :loading="dialog_site.site.loading_plugins" :items-per-page="-1" :items-per-page-options="[{'title':'All','value':-1}]" v-model="dialog_site.environment_selected.plugins_selected" item-value="name" show-select hide-default-footer>
					<template v-slot:item.status="{ item }">
						<div v-if="item.status === 'inactive' || item.status === 'active'">
						<v-switch hide-details v-model="item.status" false-value="inactive" true-value="active" @update:model-value="togglePlugin(item.name, item.status, dialog_site.site.site_id)"></v-switch>
						</div>
						<div v-else>
						{{ item.status }}
						</div>
					</template>
					<template v-slot:item.actions="{ item }">
						<v-btn variant="text" class="mx-0" @click="deletePlugin(item.name, dialog_site.site.site_id)" v-if="item.status === 'active' || item.status === 'inactive'">
						<v-icon color="pink">mdi-delete</v-icon>
						</v-btn>
					</template>
					<template v-slot:body.append>
						<tr v-for="plugin in dialog_site.environment_selected.plugins.filter(plugin => plugin.status == 'must-use' || plugin.status == 'dropin')">
						<td></td>
						<td>{{ plugin.title }}</td>
						<td>{{ plugin.name }}</td>
						<td>{{ plugin.version }}</td>
						<td>{{ plugin.status }}</td>
						<td class="text-center px-0"></td>
						</tr>
					</template>
					</v-data-table>
				</div>
				</v-card>

			</v-window-item>
			<v-window-item :key="4" value="tab-Users" :transition="false" :reverse-transition="false">
				<v-card flat>
					<v-toolbar flat density="compact" color="transparent" class="mb-2">
						<v-toolbar-title>Users</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-text-field
							v-model="users_search"
							ref="users_search"
							append-inner-icon="mdi-magnify"
							label="Search"
							density="compact"
							hide-details
							variant="outlined"
							class="me-2"
							style="max-width: 350px;"
						></v-text-field>
						<v-btn variant="text" @click="bulkEdit(dialog_site.site.site_id,'users')" v-if="dialog_site.environment_selected.users_selected.length != 0">
							Bulk Edit {{ dialog_site.environment_selected.users_selected.length }} users
						</v-btn>
					</v-toolbar>
					<v-card-text v-show="typeof dialog_site.environment_selected.users == 'string'">
						<span><v-progress-circular indeterminate color="primary" class="ma-2" size="24"></v-progress-circular></span>
					</v-card-text>
					<div v-if="typeof dialog_site.environment_selected.users != 'string'">
						<v-data-table
							:headers="header_users"
							:items="dialog_site.environment_selected.users"
							:items-per-page="50"
							:items-per-page-options="[
								{ value: 50, title: '50' },
								{ value: 100, title: '100' },
								{ value: 250, title: '250' },
								{ value: -1, title: 'All' }
							]"
							item-value="user_login"
							v-model="dialog_site.environment_selected.users_selected"
							class="table_users"
							:search="users_search"
							show-select
						>
							<template v-slot:item.roles="{ item }">
								{{ item.roles.split(",").join(" ") }}
							</template>
							<template v-slot:item.actions="{ item }">
								<v-btn variant="tonal" size="small" rounded @click="magicLoginSite(dialog_site.site.site_id, item, dialog_site.environment_selected)" :loading="item.isLoggingIn" class="my-2">Login as</v-btn>
								<v-btn variant="text" class="my-2" @click="deleteUserDialog(item.user_login, dialog_site.site.site_id)" icon="mdi-delete" color="red"></v-btn>
							</template>
						</v-data-table>
					</div>
				</v-card>
			</v-window-item>
			<v-window-item :key="5" value="tab-Updates" :transition="false" :reverse-transition="false">
				<v-toolbar density="compact" color="transparent" flat>
					<v-toolbar-title>Update Logs</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn variant="text" @click="runUpdate(dialog_site.site.site_id)">Manual update <v-icon dark>mdi-sync</v-icon></v-btn>
						<v-btn variant="text" @click="updateSettings(dialog_site.site.site_id)">Update Settings <v-icon dark>mdi-settings</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card flat>
					<v-card-text v-show="typeof dialog_site.environment_selected.update_logs == 'string'">
						<span><v-progress-circular indeterminate color="primary" class="ma-2" size="24"></v-progress-circular></span>
					</v-card-text>
					<v-card class="mx-auto mb-4" max-width="500" outlined link hover @click="getUpdateLogQuicksave( item.hash_before, item.hash_after, dialog_site.site.site_id ); item.view_quicksave = true" v-for="item in dialog_site.environment_selected.update_logs" v-if="typeof dialog_site.environment_selected.update_logs == 'object'" :key="item.name">
						<v-card-title>{{ pretty_timestamp_epoch( item.created_at ) }}<v-spacer></v-spacer><v-icon v-show="item.status == 'success'" color="success">mdi-check-circle</v-icon><v-icon v-show="item.status == 'failed'" color="error">mdi-alert-circle</v-icon></v-card-title>
						<v-card-text>
						<v-badge :content="item.themes_changed" :value="item.themes_changed" overlap class="mr-2 mb-2" color="primary">
							<v-chip label>{{ item.theme_count }} Themes</v-chip>
						</v-badge>
						<v-badge :content="item.plugins_changed" :value="item.plugins_changed" overlap class="mr-2 mb-2" color="primary">
							<v-chip label>{{ item.plugin_count }} Plugins</v-chip>
						</v-badge>
						<div>{{ item.status }}</div>
						</v-card-text>
						<v-dialog v-model="item.view_quicksave" max-width="980">
						<v-toolbar color="primary">
							<v-btn icon="mdi-close" @click="item.view_quicksave = false"></v-btn>
							<v-toolbar-title>Updates on {{ pretty_timestamp_epoch( item.created_at ) }}</v-toolbar-title>
							<v-tooltip location="bottom">
							<template v-slot:activator="{ props }">
								<v-icon v-bind="props" class="ma-3" size="small">mdi-file-compare</v-icon>
							</template>
							<span>{{ item.status }}</span>
							</v-tooltip>
							<v-btn variant="text" size="small" @click="rollbackUpdates( dialog_site.site.site_id, item, true)">Revert changes <v-icon>mdi-restore</v-icon></v-btn>
							<v-btn variant="text" size="small" @click="rollbackUpdates( dialog_site.site.site_id, item)">Reapply changes <v-icon>mdi-redo</v-icon></v-btn>
						</v-toolbar>
						<v-card v-if="item.loading" elevation="0">
							<span><v-progress-circular indeterminate color="primary" class="mx-16 mt-7 mb-7" size="24"></v-progress-circular></span>
						</v-card>
						<v-card v-else elevation="0" rounded="0">
							<v-data-table
							:headers='[{"title":"Theme","key":"title"},{"title":"Version","key":"version","width":"150px"},{"title":"Status","key":"status","width":"150px"},{"title":"","key":"rollback","width":"150px"}]'
							:items="item.themes"
							:items-per-page="-1"
							hide-default-footer
							item-value="name"
							class="quicksave-table mb-7"
							>
							<template v-slot:body="{ items }">
								<tr class="change-removed" v-for="theme in item.themes_deleted" :key="'deleted-'+theme.name">
									<td class="strikethrough">{{ theme.title || theme.name }}</td>
									<td class="strikethrough">{{ theme.version }}</td>
									<td class="strikethrough">{{ theme.status }}</td>
									<td><v-btn variant="tonal" size="small" @click="RollbackUpdate(item.hash_before, 'theme', theme.name, item.started_at)">Rollback</v-btn></td>
								</tr>
								<tr v-for="theme in items" :key="theme.name" :class="{ 'change-added': theme.changed || theme.changed_version || theme.changed_status }">
									<td>
									{{ theme.title || theme.name }}
									<v-dialog max-width="600">
										<template v-slot:activator="{ props }">
										<v-btn variant="outlined" size="small" class="ml-2" v-bind="props" v-show="theme.changed || theme.changed_version" @click="viewQuicksavesChangesItem( item, `themes/${theme.name}/` )">View Changes</v-btn>
										</template>
										<template v-slot:default="{ isActive }">
										<v-card>
											<v-toolbar color="primary">
											<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
											Changes for '{{ theme.name }}' theme
											<v-spacer></v-spacer>
											</v-toolbar>
											<v-card-text>
											<v-data-table
												:headers='[{"title":"File","key":"file"}]'
												:items="item.response"
												:items-per-page-options="[50,100,250,{'title':'All','value':-1}]"
												v-show="item.response.length > 0"
											>
												<template v-slot:body="{ items }">
													<tr v-for="i in items" :key="i">
													<td>
														<a class="v-menu__activator" @click="QuicksaveFileDiffUpdate(item.hash_after, i)" style="cursor: pointer">{{ i }}</a>
													</td>
													</tr>
												</template>
											</v-data-table>
											<v-progress-linear indeterminate rounded height="6" v-show="item.response.length == 0" class="mt-7 mb-4" color="primary"></v-progress-linear>
											</v-card-text>
										</v-card>
										</template>
									</v-dialog>
									</td>
									<td :class="{ 'change-specific': theme.changed_version }">
									{{ theme.version }}
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }"><v-icon size="small" v-show="theme.changed_version" v-bind="props">mdi-information</v-icon></template>
										<span>Changed from {{ theme.changed_version }}</span>
									</v-tooltip>
									</td>
									<td :class="{ 'change-specific': theme.changed_status }">
									{{ theme.status }}
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }"><v-icon size="small" v-show="theme.changed_status" v-bind="props">mdi-information</v-icon></template>
										<span>Changed from {{ theme.changed_status }}</span>
									</v-tooltip>
									</td>
									<td>
									<v-dialog max-width="600">
										<template v-slot:activator="{ props }">
										<v-btn variant="outlined" size="small" v-bind="props">Rollback</v-btn>
										</template>
										<template v-slot:default="{ isActive }">
										<v-card>
											<v-toolbar color="primary">
												<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
												Rollback '{{ theme.name }}' theme?
											</v-toolbar>
											<v-list>
											<v-list-item lines="two" @click="RollbackUpdate(item.hash_after, 'theme', theme.name, item.created_at, dialog)">
												<v-list-item-title>Updated version</v-list-item-title>
												<v-list-item-subtitle>{{ pretty_timestamp_epoch(item.created_at) }}</v-list-item-subtitle>
											</v-list-item>
											<v-list-item lines="two" @click="RollbackUpdate(item.hash_before, 'theme', theme.name, item.started_at, dialog)">
												<v-list-item-title>Previous version</v-list-item-title>
												<v-list-item-subtitle>{{ pretty_timestamp_epoch(item.started_at) }}</v-list-item-subtitle>
											</v-list-item>
											</v-list>
										</v-card>
										</template>
									</v-dialog>
									</td>
								</tr>
							</template>
							</v-data-table>
							<v-data-table
							:headers='[{"title":"Plugin","key":"plugin"},{"title":"Version","key":"version","width":"150px"},{"title":"Status","key":"status","width":"150px"},{"title":"","key":"rollback","width":"150px"}]'
							:items="item.plugins"
							item-value="name"
							:items-per-page="-1"
							hide-default-footer
							class="quicksave-table pb-5"
							>
							<template v-slot:body="{ items }">
								<tr class="bg-red-lighten-4" v-for="plugin in item.plugins_deleted" :key="'deleted-'+plugin.name">
									<td class="strikethrough">{{ plugin.title || plugin.name }}</td>
									<td class="strikethrough">{{ plugin.version }}</td>
									<td class="strikethrough">{{ plugin.status }}</td>
									<td><v-btn variant="outlined" size="small" @click="RollbackUpdate(item.hash_before, 'plugin', plugin.name, item.started_at)">Rollback</v-btn></td>
								</tr>
								<tr v-for="plugin in items" :key="plugin.name" :class="[{ 'change-added': plugin.changed_version || plugin.changed_status },{ 'bg-red-lighten-4 strikethrough': plugin.deleted }]">
									<td>
									{{ plugin.title || plugin.name }}
									<v-dialog max-width="600">
										<template v-slot:activator="{ props }">
										<v-btn variant="outlined" size="small" class="ml-2" v-bind="props" v-show="plugin.changed || plugin.changed_version" @click="viewQuicksavesChangesItem( item, `plugins/${plugin.name}/` )">View Changes</v-btn>
										</template>
										<template v-slot:default="{ isActive }">
										<v-card>
											<v-toolbar color="primary">
											<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
											Changes for '{{ plugin.name }}' plugin
											<v-spacer></v-spacer>
											</v-toolbar>
											<v-card-text>
											<v-data-table
												:headers='[{"title":"File","key":"file"}]'
												:items="item.response"
												:items-per-page-options="[50,100,250,{'title':'All','value':-1}]"
												v-show="item.response.length > 0"
											>
												<template v-slot:body="{ items }">
													<tr v-for="i in items" :key="i">
													<td>
														<a class="v-menu__activator" @click="QuicksaveFileDiffUpdate(item.hash_after, i)" style="cursor: pointer">{{ i }}</a>
													</td>
													</tr>
												</template>
											</v-data-table>
											<v-progress-linear indeterminate rounded height="6" v-show="item.response.length == 0" class="mt-7 mb-4" color="primary"></v-progress-linear>
											</v-card-text>
										</v-card>
										</template>
									</v-dialog>
									</td>
									<td :class="{ 'change-specific': plugin.changed_version }">
									{{ plugin.version }}
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }"><v-icon size="small" v-show="plugin.changed_version" v-bind="props">mdi-information</v-icon></template>
										<span>Changed from {{ plugin.changed_version }}</span>
									</v-tooltip>
									</td>
									<td :class="{ 'change-specific': plugin.changed_status }">
									{{ plugin.status }}
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }"><v-icon size="small" v-show="plugin.changed_status" v-bind="props">mdi-information</v-icon></template>
										<span>Changed from {{ plugin.changed_status }}</span>
									</v-tooltip>
									</td>
									<td>
									<v-dialog max-width="600">
										<template v-slot:activator="{ props }">
										<v-btn variant="outlined" size="small" v-bind="props" v-show="plugin.status != 'must-use' && plugin.status != 'dropin'">Rollback</v-btn>
										</template>
										<template v-slot:default="{ isActive }">
										<v-card>
											<v-toolbar color="primary">
												<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
												Rollback '{{ plugin.name }}' plugin?
											</v-toolbar>
											<v-list>
											<v-list-item lines="two" @click="RollbackUpdate(item.hash_after, 'plugin', plugin.name, item.created_at, dialog)">
												<v-list-item-title>Updated version <span v-show="plugin.changed_version" v-text="plugin.version"></span></v-list-item-title>
												<v-list-item-subtitle>{{ pretty_timestamp_epoch(item.created_at) }}</v-list-item-subtitle>
											</v-list-item>
											<v-list-item lines="two" @click="RollbackUpdate(item.hash_before, 'plugin', plugin.name, item.started_at, dialog)">
												<v-list-item-title>Previous version <span v-show="plugin.changed_version" v-text="plugin.started_at"></span></v-list-item-title>
												<v-list-item-subtitle>{{ pretty_timestamp_epoch(item.previous_created_at) }}</v-list-item-subtitle>
											</v-list-item>
											</v-list>
										</v-card>
										</template>
									</v-dialog>
									</td>
								</tr>
							</template>
							</v-data-table>
						</v-card>
						</v-dialog>
					</v-card>
				</v-card>
			</v-window-item>
			<v-window-item :key="6" value="tab-Scripts" :transition="false" :reverse-transition="false">
				<v-card flat color="transparent">
					<v-card-text>
						
						<!-- 1. TERMINAL INTEGRATION BAR -->
						<v-card variant="tonal" color="primary" class="mb-6" rounded="lg">
							<v-card-text class="d-flex align-center py-3">
								<v-icon start size="large" class="mr-4">mdi-console-line</v-icon>
								<div>
									<div class="text-subtitle-1 font-weight-bold">Command Console</div>
									<div class="text-caption">Run WP-CLI commands, Bash scripts, or Recipes on {{ dialog_site.environment_selected.environment }}.</div>
								</div>
								<v-spacer></v-spacer>
								<v-btn color="primary" variant="flat" @click="openTerminalForCurrentEnv()">
									Open Terminal
									<v-icon end>mdi-open-in-new</v-icon>
								</v-btn>
							</v-card-text>
						</v-card>

						<!-- 2. SYSTEM TOOLS GRID -->
						<v-row class="mb-2">
							<v-col cols="12">
								<h3 class="text-subtitle-2 text-medium-emphasis text-uppercase mb-2">System Tools</h3>
							</v-col>
							
							<v-col cols="12" sm="6" md="4">
								<v-card hover border="thin" @click="siteDeploy(dialog_site.site.site_id)" height="100%">
									<v-list-item lines="two">
										<template v-slot:prepend>
											<v-avatar color="blue-lighten-5" class="rounded-lg" variant="flat">
												<v-icon color="blue-darken-2">mdi-refresh</v-icon>
											</v-avatar>
										</template>
										<v-list-item-title class="font-weight-bold">Deploy Defaults</v-list-item-title>
										<v-list-item-subtitle>Apply standard config & plugins</v-list-item-subtitle>
									</v-list-item>
								</v-card>
							</v-col>

							<v-col cols="12" sm="6" md="4">
								<v-card hover border="thin" @click="viewApplyHttpsUrls(dialog_site.site.site_id)" height="100%">
									<v-list-item lines="two">
										<template v-slot:prepend>
											<v-avatar color="green-lighten-5" class="rounded-lg" variant="flat">
												<v-icon color="green-darken-2">mdi-lock-check</v-icon>
											</v-avatar>
										</template>
										<v-list-item-title class="font-weight-bold">Apply HTTPS</v-list-item-title>
										<v-list-item-subtitle>Search & replace http:// to https://</v-list-item-subtitle>
									</v-list-item>
								</v-card>
							</v-col>

							<v-col cols="12" sm="6" md="4">
								<v-card hover border="thin" @click="resetPermissions(dialog_site.site.site_id)" height="100%">
									<v-list-item lines="two">
										<template v-slot:prepend>
											<v-avatar color="orange-lighten-5" class="rounded-lg" variant="flat">
												<v-icon color="orange-darken-2">mdi-file-lock</v-icon>
											</v-avatar>
										</template>
										<v-list-item-title class="font-weight-bold">Reset Permissions</v-list-item-title>
										<v-list-item-subtitle>Fix file ownership & groups</v-list-item-subtitle>
									</v-list-item>
								</v-card>
							</v-col>

							<v-col cols="12" sm="6" md="4">
								<v-card hover border="thin" @click="toggleSite(dialog_site.site.site_id)" height="100%">
									<v-list-item lines="two">
										<template v-slot:prepend>
											<v-avatar color="grey-lighten-3" class="rounded-lg" variant="flat">
												<v-icon color="grey-darken-3">mdi-toggle-switch</v-icon>
											</v-avatar>
										</template>
										<v-list-item-title class="font-weight-bold">Maintenance Mode</v-list-item-title>
										<v-list-item-subtitle>Toggle site accessibility</v-list-item-subtitle>
									</v-list-item>
								</v-card>
							</v-col>

							<v-col cols="12" sm="6" md="4">
								<v-card hover border="thin" @click="launchSiteDialog(dialog_site.site.site_id)" height="100%">
									<v-list-item lines="two">
										<template v-slot:prepend>
											<v-avatar color="purple-lighten-5" class="rounded-lg" variant="flat">
												<v-icon color="purple-darken-2">mdi-rocket-launch</v-icon>
											</v-avatar>
										</template>
										<v-list-item-title class="font-weight-bold">Launch Site</v-list-item-title>
										<v-list-item-subtitle>Go live domain replacement</v-list-item-subtitle>
									</v-list-item>
								</v-card>
							</v-col>

							<v-col cols="12" sm="6" md="4">
								<v-card hover border="thin" @click="showSiteMigration(dialog_site.site.site_id)" height="100%">
									<v-list-item lines="two">
										<template v-slot:prepend>
											<v-avatar color="teal-lighten-5" class="rounded-lg" variant="flat">
												<v-icon color="teal-darken-2">mdi-truck-fast</v-icon>
											</v-avatar>
										</template>
										<v-list-item-title class="font-weight-bold">Migrate Backup</v-list-item-title>
										<v-list-item-subtitle>Import from external URL</v-list-item-subtitle>
									</v-list-item>
								</v-card>
							</v-col>
						</v-row>

						<v-divider class="my-6"></v-divider>

						<!-- 3. RECIPES (If available) -->
						<div v-show="recipes.length > 0">
							<v-row class="mb-2">
								<v-col cols="12" class="d-flex align-center">
									<h3 class="text-subtitle-2 text-medium-emphasis text-uppercase">Recipes</h3>
								</v-col>
								<v-col cols="12" sm="6" md="4" v-for="recipe in recipes" :key="recipe.recipe_id">
									<v-card hover border="thin" @click="handleRecipeClick(recipe)" height="100%" density="compact">
										<v-card-text class="d-flex align-center">
											<!-- Icon: Package for Public, Script for Private -->
											<v-icon size="small" class="mr-2" :class="recipe.public == 1 ? 'text-primary' : 'text-medium-emphasis'">
												{{ recipe.public == 1 ? 'mdi-package-variant-closed' : 'mdi-script-text-outline' }}
											</v-icon>
											
											<span class="text-body-2 font-weight-medium text-truncate">{{ recipe.title }}</span>
											
											<v-spacer></v-spacer>
											
											<!-- Status Icon: Lock for Public, Console for Private -->
											<v-icon v-if="recipe.public == 1" size="x-small" class="text-disabled" title="System Package (Runs Immediately)">mdi-lock-outline</v-icon>
											<v-icon v-else size="x-small" class="text-medium-emphasis" title="Load into Console">mdi-console-line</v-icon>
										</v-card-text>
									</v-card>
								</v-col>
							</v-row>
							<v-divider class="my-6"></v-divider>
						</div>

						<!-- 4. SCHEDULED SCRIPTS HISTORY -->
						<div v-if="dialog_site.environment_selected.scheduled_scripts.length > 0">
							<h3 class="text-subtitle-2 text-medium-emphasis text-uppercase mb-2">Scheduled Scripts</h3>
							<v-card flat border="thin" rounded="lg">
								<v-data-table 
									:headers='[ {"title":"","value":"icon","sortable":false,"width":"56"}, {"title":"Code","value":"code","sortable":false}, {"title":"User","value":"author","sortable":false,"width":"180"}, {"title":"Run Date","value":"run_at","sortable":false,"width":"220"}, {"title":"","value":"actions","sortable":false,"width":"50"}]' 
									:items="dialog_site.environment_selected.scheduled_scripts" 
									hide-default-footer 
									:footer-props="{ itemsPerPageOptions: [50,100,250,{'text':'All','value':-1}] }"
								>
									<template v-slot:item.icon>
										<div class="text-center"><v-icon color="primary" size="small">mdi-clock-outline</v-icon></div>
									</template>
									<template v-slot:item.code="{ item }">
										<code class="text-caption bg-grey-lighten-4 pa-1 rounded">{{ previewCode( item.code ) }}</code>
									</template>
									<template v-slot:item.author="{ item }">
										<div class="d-flex align-center">
											<v-avatar size="24" class="mr-2"><v-img :src="item.author_avatar"></v-img></v-avatar>
											<span class="text-caption">{{ item.author }}</span>
										</div>
									</template>
									<template v-slot:item.run_at="{ item }">
										<span class="text-caption">{{ pretty_timestamp_epoch( item.run_at ) }}</span>
									</template>
									<template v-slot:item.actions="{ item }">
										<v-btn variant="text" icon density="compact" @click="editScript(item)">
											<v-icon size="small">mdi-pencil</v-icon>
										</v-btn>
									</template>
								</v-data-table>
							</v-card>
						</div>
						
						<!-- Empty State for History -->
						<div v-else class="text-center py-4 text-caption text-medium-emphasis">
							No scheduled scripts found for this environment.
						</div>

					</v-card-text>
				</v-card>
			</v-window-item>
			<v-window-item :key="7" value="tab-Backups" :transition="false" :reverse-transition="false">
				<v-toolbar density="compact" color="transparent" flat>
					<v-toolbar-title>Backups</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
                        <v-btn variant="text" @click="promptBackupSnapshot( dialog_site.site.site_id )">Download Snapshot <v-icon dark>mdi-cloud-download</v-icon></v-btn>
                    	<v-btn variant="text" @click="QuicksaveCheck( dialog_site.site.site_id )">New Quicksave <v-icon dark>mdi-sync</v-icon></v-btn>
						<v-btn variant="text" @click="dialog_backup_configurations.settings = dialog_site.site.backup_settings; dialog_backup_configurations.show = true" v-show="role == 'administrator'"><v-icon dark small>mdi-pencil</v-icon> Edit</v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-sheet v-show="dialog_site.backup_step == 1">
				  <v-card flat>
				<v-row class="pa-4">
				<v-col cols="12" md="4" class="px-2">
				<v-card
					class="mx-auto"
					max-width="344"
					variant="outlined"
					border="thin"
					link
					hover
					:href="`${configurations.path}sites/${dialog_site.site.site_id}/backups`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/backups`)"
				>
					<v-card-title>Backups</v-card-title>
					<v-card-subtitle style="white-space: normal;">Original file and database backups.</v-card-subtitle>
					<v-card-text>
						<span v-if="typeof dialog_site.environment_selected.details.backup_count == 'number'">{{ dialog_site.environment_selected.details.backup_count }} backups</v-show>
					</v-card-text>
				</v-card>
				</v-col>
				<v-col cols="12" md="4" class="px-2" v-show="dialog_site.environment_selected.token != 'basic'">
				<v-card
					class="mx-auto"
					max-width="344"
					variant="outlined"
					border="thin"
					link
					hover
					:href="`${configurations.path}sites/${dialog_site.site.site_id}/quicksaves`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/quicksaves`)"
				>
					<v-card-title>Quicksaves</v-card-title>
					<v-card-subtitle style="white-space: normal;">Know what changed and when. Easily rollback themes or plugins. Super helpful for troubleshooting maintenance issues.</v-card-subtitle>
					<v-card-text>
						<span v-if="typeof dialog_site.environment_selected.details.quicksave_usage == 'object'">{{ dialog_site.environment_selected.details.quicksave_usage.count }} quicksaves</v-show>
					</v-card-text>
				</v-card>
				</v-col>
				<v-col cols="12" md="4" class="px-2">
				<v-card
					class="mx-auto"
					max-width="344"
					variant="outlined"
					border="thin"
					link
					hover
					:href="`${configurations.path}sites/${dialog_site.site.site_id}/snapshots`" @click.prevent="goToPath(`/sites/${dialog_site.site.site_id}/snapshots`)"
				>
					<v-card-title>Snapshots</v-card-title>
					<v-card-subtitle style="white-space: normal;">Manually generated snapshots zips.</v-card-subtitle>
					<v-card-text>
						<span v-if="typeof dialog_site.environment_selected.details.snapshot_count == 'number'">{{ dialog_site.environment_selected.details.snapshot_count }} snapshots</v-show>
					</v-card-text>
				</v-card>
				</v-col>
				</v-row>
				</v-card>
				</v-sheet>
				<v-sheet v-show="dialog_site.backup_step == 2">
				<v-card flat>
					<v-list-subheader><a @click="dialog_site.backup_step = 1" class="ml-5">Types</a>&nbsp;/ Backups</v-list-subheader>
					<v-card-text v-if="typeof dialog_site.environment_selected.backups == 'string'">
						<span><v-progress-circular indeterminate color="primary" class="ma-2" size="24"></v-progress-circular></span>
					</v-card-text>
					<div v-else>
					<v-data-table
						:headers="[{title:'Created At', key:'time'}, {title:'Backup ID', key:'short_id', width:'120px'}]"
						:items="dialog_site.environment_selected.backups"
						item-value="id"
						no-data-text="No backups found."
						:ref="'backup_table_'+ dialog_site.site.site_id + '_' + dialog_site.environment_selected.environment"
						show-expand
						class="table-backups"
						v-model:expanded="dialog_site.environment_selected.expanded_backups"
						@click:row="(event, { item }) => handleRowClick(item)"
					>
						<template v-slot:item.time="{ item }">
							{{ pretty_timestamp( item.time ) }}
						</template>
						<template v-slot:expanded-row="{ item }"> <td colspan="3" style="position: relative; padding:0px">
								<v-row no-gutters justify="space-between">
									<v-col cols="4" md="4" sm="12">
										<v-progress-circular indeterminate color="primary" class="ma-5" size="24" v-show="item.loading"></v-progress-circular>
										<v-treeview
											v-model:selected="item.tree"
											v-model:activated="item.active"
											:items="item.files"
											:load-children="handleLoadChildren"
											activatable
											selectable
											select-strategy="independent"
											selected-color="primary"
											item-value="path"
											item-title="name"
											density="compact"
											color="primary"
											open-on-click
											@update:activated="previewFile(item)"
											@update:selected="newPaths => handleTreeSelection(item, newPaths)"
										>
											<template v-slot:prepend="{ item: nodeItem, open }">
												<v-icon v-if="nodeItem.type == 'dir'">
													{{ open ? 'mdi-folder-open' : 'mdi-folder' }}
												</v-icon>
												<v-icon v-else>
													{{ files[nodeItem.ext] ? files[nodeItem.ext] : 'mdi-file' }}
												</v-icon>
											</template>
										</v-treeview>
									</v-col>
									<v-col class="flex-grow-0 flex-shrink-0 border-e"></v-col>
									<v-col class="flex-grow-1 flex-shrink-0 text-center">
										<v-alert type="info" density="compact" variant="text" v-show="item.omitted">This backup has too many files to show. Uploaded files have been omitted for viewing purposes. Everything is still restorable.</v-alert>
											<v-card v-if="item.active_node" class="pt-3 pt-6" flat>
												<div v-if="item.active_node.type === 'dir'">
													<v-card-text>
														<h3 class="text-h6 mb-2">
															<v-icon start>mdi-folder-outline</v-icon>
															Folder: {{ item.active_node.name }}
														</h3>
														<p class="text-body-1 mt-4">
															Contains {{ item.active_node.stats.fileCount }} files
														</p>
														<p class="text-body-1">
															Total Size: {{ formatSize(item.active_node.stats.totalSize) }}
														</p>
													</v-card-text>
													<v-card-actions class="justify-center">
														<p class="mt-5"><v-btn variant="tonal" @click="item.active = []; item.active_node = null">Close</v-btn></p>
													</v-card-actions>
												</div>

												<div v-else-if="item.active_node.type === 'file'">
													<v-card-text>
														<h3 class="text-h6 mb-2" ref="filePreviewTitle">
															Previewing {{ item.active_node.name }}
														</h3>
														<p>{{ formatSize( item.active_node.size ) }}</p>
													</v-card-text>
													<div v-if="item.preview == ''">
														<v-divider></v-divider>
														<v-progress-circular indeterminate color="primary" class="ma-2" size="24"></v-progress-circular>
													</div>
													<p v-else-if="item.preview == 'too-large'">File too large to preview.</p>
													<v-card v-else-if="item.active_node.ext === 'svg'" class="text-center overflow-auto" flat style="max-width: 950px;font-size: 10px;zoom: 0.8; margin-left: 20px;">
														<div v-html="item.preview" style="max-width:400px; height: auto; overflow: auto; margin:auto;"></div>
													</v-card>
													<v-card v-else-if="item.isPreviewImage" class="text-center overflow-auto" flat style="max-width: 950px;font-size: 10px;zoom: 0.8; margin-left: 20px;">
														<img :src="item.preview" style="max-width:100%;">
													</v-card>
													<v-card v-else class="text-left bg-black overflow-auto" flat style="max-width: 950px;font-size: 10px;zoom: 0.8; margin-left: 20px;">
														<pre class="line-numbers"><code :class="'language-' + (item.active_node.ext || 'markup')" v-html="item.preview"></code></pre>
													</v-card>
													<v-card-actions class="justify-center">
														<p class="mt-5">
															<v-btn variant="tonal" @click="item.active = []; item.active_node = null">Close preview</v-btn>
															<v-btn v-if="item.isPreviewImage" variant="tonal" color="primary" :href="item.preview" :download="item.active_node.name" class="ml-2" :disabled="item.preview == ''">Download</v-btn>
														</p>
													</v-card-actions>
												</div>
											</v-card>
											<div v-else-if="item.tree && item.tree.length == 0" class="text-h6 font-weight-light mt-5" style="align-self: center;">
												Select a file or folder.<br />
												<a class="text-body-2" @click="selectAllInBackup(item)">Select everything</a>
											</div>
											<v-card
												v-else-if="item.tree && item.tree.length > 0" class="pt-6 mx-auto"
												flat
												max-width="400"
											>
												<v-card-text>
													<h3 class="text-h6 mb-2"> {{ item.tree.length }} items selected</h3>
													<p>{{ formatSize ( item.calculated_total ) }}</p>
												</v-card-text>
												<v-divider></v-divider>
												<v-row class="mt-5">
													<v-col class="text-center" cols="12">
														<v-btn variant="tonal" @click="downloadBackup( item.id, item.tree )">Download<v-icon end icon="mdi-file-download"></v-icon></v-btn>
													</v-col>
												</v-row>
												<v-row>
													<v-col class="text-center" cols="12">
														<a @click="item.tree = []" style="cursor: pointer;">Cancel selection</a>
													</v-col>
												</v-row>
											</v-card>
									</v-col>
								</v-row>
							</td>
						</template>
					</v-data-table>
					</div>
				</v-card>
				</v-sheet>
				<v-sheet v-show="dialog_site.backup_step == 3">
				<v-card elevation="0">
				<v-list-subheader><a @click="dialog_site.backup_step = 1">Types</a>&nbsp;/ Quicksaves</v-list-subheader>
				<v-card-text v-if="typeof dialog_site.environment_selected.quicksaves == 'string'">
					<span><v-progress-circular indeterminate color="primary" class="ma-2" size="24"></v-progress-circular></span>
				</v-card-text>
				<div v-else>
					<v-toolbar elevation="0" color="transparent">
						<v-spacer></v-spacer>
						<v-select hide-details variant="outlined" :items="[{title: 'Themes', value: 'theme'}, {title: 'Plugins', value: 'plugin'}]" v-model="quicksave_search_type" label="Search for" density="compact" style="max-width: 125px" class="mr-2"></v-select>
						<v-select hide-details variant="outlined" :items="[{title: 'Slug', value: 'name'}, {title: 'Title', value: 'title'}, {title: 'Status', value: 'status'}, {title: 'Version', value: 'version'}]" v-model="quicksave_search_field" label="By" density="compact" style="max-width: 125px" class="mr-2"></v-select>
						<v-text-field hide-details variant="outlined" v-model="quicksave_search" density="compact" autofocus label="Search historical activity" clearable hide-details append-inner-icon="mdi-magnify" @keydown.enter="searchQuicksave" @click:append="searchQuicksave" style="max-width:375px;"></v-text-field>
					</v-toolbar>
					<div v-show="quicksave_search_results.loading" class="text-body-2 mx-5"><v-progress-circular indeterminate color="primary" class="ma-2" size="24"></v-progress-circular> searching quicksaves</div>
					<v-card v-if="quicksave_search_results.items.length > 0" class="ma-4">
					<v-app-bar elevation="0" density="compact">
						<v-card-title> {{ quicksave_search_results.items.length }} search results </v-card-title>
						<v-spacer></v-spacer>
						<v-btn icon="mdi-close" @click='quicksave_search_results = { loading: false, search: "", search_type: "", search_field: "", items: [] }'></v-btn>
					</v-app-bar>
					<v-card-text>
						<v-data-table
						:headers="[{title:'Created At',key:'created_at'},{title:'Item',key:'item'},{title:'',key:'actions'}]"
						:items="quicksave_search_results.items"
						item-value="hash"
						no-data-text="No quicksaves found."
						:items-per-page-options="[25,50,100,{'title':'All','value':-1}]">
						<template v-slot:item.created_at="{ item }">
							{{ pretty_timestamp_epoch( item.created_at ) }}
						</template>
						<template v-slot:item.item="{ item }">
							<span v-if="item.item == ''">
							{{ quicksave_search_results.search }} not found
							</span>
							<span v-else>
							{{ item.item.title }} {{ item.item.version }} {{ item.item.status }}
							</span>
						</template>
						<template v-slot:item.actions="{ item }">
							<v-dialog max-width="600">
							<template v-slot:activator="{ props }">
								<v-btn variant="tonal" size="small" v-bind="props" v-if="item.item != ''">Rollback</v-btn>
							</template>
							<template v-slot:default="{ isActive }">
								<v-card>
								<v-toolbar color="primary">
									<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
									Rollback '{{ item.item.title }}' {{ quicksave_search_results.search_type }}?									
								</v-toolbar>
								<v-list>
									<v-list-item lines="two" @click="RollbackQuicksave(item.hash, quicksave_search_results.search_type, item.item.name, 'this', dialog)">
									<v-list-item-title>This version {{ item.item.version }}</v-list-item-title>
									<v-list-item-subtitle>{{ pretty_timestamp_epoch(item.created_at) }}</v-list-item-subtitle>
									</v-list-item>
								</v-list>
								</v-card>
							</template>
							</v-dialog>
						</template>
						</v-data-table>
					</v-card-text>
					</v-card>
					<v-data-table
						:headers="[{title:'Created At',key:'created_at'},{title:'WordPress',key:'core',width:'115px'},{title:'',key:'theme_count',width:'115px'},{title:'',key:'plugin_count',width:'115px'}]"
						:items="dialog_site.environment_selected.quicksaves"
						item-value="hash"
						no-data-text="No quicksaves found."
						:ref="'quicksave_table_'+ dialog_site.site.site_id + '_' + dialog_site.environment_selected.environment"
						@click:row="(event, { item }) => getQuicksave( item.hash, dialog_site.site.site_id )"
						@update:expanded="handleExpansionUpdate"
						show-expand
						expand-on-click
						:items-per-page-options="[25,50,100,{'title':'All','value':-1}]"
						class="table-quicksaves"
						:expanded="expanded"
					>
					<template v-slot:item.created_at="{ item }">
						{{ pretty_timestamp_epoch( item.created_at ) }}
					</template>
					<template v-slot:item.core="{ item }">
						{{ item.core }}
					</template>
					<template v-slot:item.theme_count="{ item }">
						{{ item.theme_count }} themes
					</template>
					<template v-slot:item.plugin_count="{ item }">
						{{ item.plugin_count }} plugins
					</template>
					<template v-slot:expanded-row="{ columns, item }">
						<tr class="v-data-table__expanded">
						<td :colspan="columns.length" style="position: relative; padding:0px" v-if="item.loading">
							<span><v-progress-circular indeterminate color="primary" class="mx-16 mt-3 mb-7" size="24"></v-progress-circular></span>
						</td>
						<td :colspan="columns.length" class="pa-5" style="position: relative;" v-else>
						<v-toolbar color="primary" density="compact" class="elevation-1" style="border-radius: 4px 4px 0 0;">
							<v-toolbar-title class="text-body-2">{{ item.status }}</v-toolbar-title>
							<v-btn variant="text" size="small" @click="QuicksavesRollback( dialog_site.site.site_id, item, 'previous' )" v-show="item.previous_created_at">Revert changes <v-icon>mdi-restore</v-icon></v-btn>
							<v-btn variant="text" size="small" @click="QuicksavesRollback( dialog_site.site.site_id, item, 'this' )">Reapply changes <v-icon>mdi-redo</v-icon></v-btn>
							<v-btn variant="text" size="small" @click="viewQuicksavesChanges( dialog_site.site.site_id, item)">View Changes <v-icon>mdi-file-compare</v-icon></v-btn>
						</v-toolbar>
						<v-dialog fullscreen scrim="false" v-model="item.view_changes">
							<v-card rounded="0">
								<v-toolbar color="primary" density="compact">
									<v-btn icon="mdi-close" @click="item.view_changes = false"></v-btn>
									<v-toolbar-title>List of changes</v-toolbar-title>
								</v-toolbar>
								<v-card-text>
									<v-row no-gutters align="center">
										<v-col>
											<v-card-title class="px-0">Files</v-card-title>
										</v-col>
										<v-col cols="12" sm="4" md="3">
											<v-text-field
												v-model="item.search"
												ref="quicksave_search"
												@update:model-value="filterFiles(dialog_site.site.site_id, item.hash)"
												append-inner-icon="mdi-magnify"
												label="Search"
												density="compact"
												variant="outlined"
												hide-details
												clearable
											></v-text-field>
										</v-col>
									</v-row>
									<v-data-table
										:headers='[{"title":"File","key":"file"}]'
										:items="item.filtered_files"
										:loading="item.loading"
										:items-per-page-options="[50,100,250,{'title':'All','value':-1}]"
										class="mt-4"
									>
										<template v-slot:item="{ item: file }">
											<tr>
												<td>
													<a style="cursor: pointer;" @click="QuicksaveFileDiff(item.hash, file)">{{ file }}</a>
												</td>
											</tr>
										</template>
									</v-data-table>
								</v-card-text>
							</v-card>
						</v-dialog>
						<v-card class="elevation-1 mx-0 mb-3">
							<v-data-table
							:headers='[{"title":"Theme","key":"title"},{"title":"Version","key":"version","width":"150px"},{"title":"Status","key":"status","width":"150px"},{"title":"","key":"rollback","width":"150px"}]'
							:items="item.themes"
							item-value="name"
							:items-per-page="-1"
							hide-default-footer
							class="quicksave-table mb-5"
							>
							<template v-slot:body="{ items }">
								<tr class="bg-red-lighten-4" v-for="theme in item.themes_deleted" :key="'deleted-'+theme.name">
									<td class="strikethrough">{{ theme.title || theme.name }}</td>
									<td class="strikethrough">{{ theme.version }}</td>
									<td class="strikethrough">{{ theme.status }}</td>
									<td><v-btn variant="outlined" size="small" @click="RollbackQuicksave(item.hash, 'theme', theme.name, 'previous')">Rollback</v-btn></td>
								</tr>
								<tr v-for="theme in items" :key="theme.name" :class="{ 'change-added': theme.changed || theme.changed_version || theme.changed_status }">
									<td>
									<v-chip color="primary" label size="x-small" v-show="theme.new" class="mr-2">New</v-chip> {{ theme.title || theme.name }}
									<v-dialog max-width="600">
										<template v-slot:activator="{ props }">
										<v-btn variant="outlined" size="small" class="ml-2" v-bind="props" v-show="theme.changed || theme.changed_version" @click="viewQuicksavesChangesItem( item, `themes/${theme.name}/` )">View Changes</v-btn>
										</template>
										<template v-slot:default="{ isActive }">
										<v-card>
											<v-toolbar color="primary">
											<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
											Changes for '{{ theme.name }}' theme
											<v-spacer></v-spacer>
											</v-toolbar>
											<v-card-text>
											<v-data-table
												:headers='[{"title":"File","key":"file"}]'
												:items="item.response"
												:items-per-page-options="[50,100,250,{'title':'All','value':-1}]"
												v-show="item.response.length > 0"
											>
												<template v-slot:body="{ items }">
													<tr v-for="i in items" :key="i">
													<td>
														<a class="v-menu__activator" @click="QuicksaveFileDiff(item.hash, i)" style="cursor: pointer">{{ i }}</a>
													</td>
													</tr>
												</template>
											</v-data-table>
											<v-progress-linear indeterminate rounded height="6" v-show="item.response.length == 0" class="mt-7 mb-4"></v-progress-linear>
											</v-card-text>
										</v-card>
										</template>
									</v-dialog>
									</td>
									<td :class="{ 'change-specific': theme.changed_version }">
									{{ theme.version }}
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }"><v-icon size="small" v-show="theme.changed_version" v-bind="props">mdi-information</v-icon></template>
										<span>Changed from {{ theme.changed_version }}</span>
									</v-tooltip>
									</td>
									<td :class="{ 'change-specific': theme.changed_status }">
									{{ theme.status }}
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }"><v-icon size="small" v-show="theme.changed_status" v-bind="props">mdi-information</v-icon></template>
										<span>Changed from {{ theme.changed_status }}</span>
									</v-tooltip>
									</td>
									<td>
									<v-dialog max-width="600">
										<template v-slot:activator="{ props }">
										<v-btn variant="outlined" size="small" v-bind="props">Rollback</v-btn>
										</template>
										<template v-slot:default="{ isActive }">
										<v-card>
											<v-toolbar color="primary">
												<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
												Rollback '{{ theme.name }}' theme?
											</v-toolbar>
											<v-list>
											<v-list-item lines="two" @click="RollbackQuicksave(item.hash, 'theme', theme.name, 'this', dialog)">
												<v-list-item-title>This version</v-list-item-title>
												<v-list-item-subtitle>{{ pretty_timestamp_epoch(item.created_at) }}</v-list-item-subtitle>
											</v-list-item>
											<v-list-item lines="two" @click="RollbackQuicksave(item.hash, 'theme', theme.name, 'previous', dialog)" v-show="item.previous_created_at">
												<v-list-item-title>Previous version</v-list-item-title>
												<v-list-item-subtitle>{{ pretty_timestamp_epoch(item.previous_created_at) }}</v-list-item-subtitle>
											</v-list-item>
											</v-list>
										</v-card>
										</template>
									</v-dialog>
									</td>
								</tr>
							</template>
							</v-data-table>
							<v-data-table
							:headers='[{"title":"Plugin","key":"plugin"},{"title":"Version","key":"version","width":"150px"},{"title":"Status","key":"status","width":"150px"},{"title":"","key":"rollback","width":"150px"}]'
							:items="item.plugins"
							item-value="name"
							class="quicksave-table"
							:items-per-page="-1"
							hide-default-footer
							>
							<template v-slot:body="{ items }">
								<tr class="bg-red-lighten-4" v-for="plugin in item.plugins_deleted" :key="'deleted-'+plugin.name">
									<td class="strikethrough">{{ plugin.title || plugin.name }}</td>
									<td class="strikethrough">{{ plugin.version }}</td>
									<td class="strikethrough">{{ plugin.status }}</td>
									<td><v-btn variant="outlined" size="small" @click="RollbackQuicksave(item.hash, 'plugin', plugin.name, 'previous')">Rollback</v-btn></td>
								</tr>
								<tr v-for="plugin in items" :key="plugin.name" :class="[{ 'change-added': plugin.changed || plugin.changed_version || plugin.changed_status },{ 'bg-red-lighten-4 strikethrough': plugin.deleted }]">
									<td>
									<v-chip color="primary" label size="x-small" v-show="plugin.new" class="mr-2">New</v-chip> {{ plugin.title || plugin.name }}
									<v-dialog max-width="600">
										<template v-slot:activator="{ props }">
										<v-btn variant="outlined" size="small" class="ml-2" v-bind="props" v-show="plugin.changed || plugin.changed_version" @click="viewQuicksavesChangesItem( item, `plugins/${plugin.name}/` )">View Changes</v-btn>
										</template>
										<template v-slot:default="{ isActive }">
										<v-card>
											<v-toolbar color="primary">
											<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
											Changes for '{{ plugin.name }}' plugin
											<v-spacer></v-spacer>
											</v-toolbar>
											<v-card-text>
											<v-data-table
												:headers='[{"title":"File","key":"file"}]'
												:items="item.response"
												:items-per-page-options="[50,100,250,{'title':'All','value':-1}]"
												v-show="item.response.length > 0"
											>
												<template v-slot:body="{ items }">
													<tr v-for="i in items" :key="i">
													<td>
														<a class="v-menu__activator" @click="QuicksaveFileDiff(item.hash, i)" style="cursor: pointer">{{ i }}</a>
													</td>
													</tr>
												</template>
											</v-data-table>
											<v-progress-linear indeterminate rounded height="6" v-show="item.response.length == 0" class="mt-7 mb-4"></v-progress-linear>
											</v-card-text>
										</v-card>
										</template>
									</v-dialog>
									</td>
									<td :class="{ 'change-specific': plugin.changed_version }">
									{{ plugin.version }}
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }"><v-icon size="small" v-show="plugin.changed_version" v-bind="props">mdi-information</v-icon></template>
										<span>Changed from {{ plugin.changed_version }}</span>
									</v-tooltip>
									</td>
									<td :class="{ 'change-specific': plugin.changed_status }">
									{{ plugin.status }}
									<v-tooltip location="bottom">
										<template v-slot:activator="{ props }"><v-icon size="small" v-show="plugin.changed_status" v-bind="props">mdi-information</v-icon></template>
										<span>Changed from {{ plugin.changed_status }}</span>
									</v-tooltip>
									</td>
									<td>
									<v-dialog max-width="600">
										<template v-slot:activator="{ props }">
										<v-btn variant="outlined" size="small" v-bind="props" v-show="plugin.status != 'must-use' && plugin.status != 'dropin'">Rollback</v-btn>
										</template>
										<template v-slot:default="{ isActive }">
										<v-card>
											<v-toolbar color="primary">
												<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
												Rollback '{{ plugin.name }}' plugin?
											</v-toolbar>
											<v-list>
											<v-list-item lines="two" @click="RollbackQuicksave(item.hash, 'plugin', plugin.name, 'this', dialog)">
												<v-list-item-title>This version <span v-show="plugin.changed_version" v-text="plugin.version"></span></v-list-item-title>
												<v-list-item-subtitle>{{ pretty_timestamp_epoch(item.created_at) }}</v-list-item-subtitle>
											</v-list-item>
											<v-list-item lines="two" @click="RollbackQuicksave(item.hash, 'plugin', plugin.name, 'previous', dialog)" v-show="item.previous_created_at">
												<v-list-item-title>Previous version <span v-show="plugin.changed_version" v-text="plugin.changed_version"></span></v-list-item-title>
												<v-list-item-subtitle>{{ pretty_timestamp_epoch(item.previous_created_at) }}</v-list-item-subtitle>
											</v-list-item>
											</v-list>
										</v-card>
										</template>
									</v-dialog>
									</td>
								</tr>
							</template>
							</v-data-table>
						</v-card>
						</td>
					</tr>
					</template>
					</v-data-table>
				</div>
				</v-card>
				</v-sheet>
					<v-sheet v-show="dialog_site.backup_step == 4">
					<v-card flat>
					<v-list-subheader><a @click="dialog_site.backup_step = 1">Types </a>&nbsp;/ Snapshots</v-list-subheader>
					<v-card-text v-if="typeof dialog_site.environment_selected.snapshots == 'string'">
						<span><v-progress-circular indeterminate color="primary" class="ma-2" size="24"></v-progress-circular></span>
					</v-card-text>
					<div v-else>
					<v-data-table
						:headers="[{title:'Created At',value:'created_at',width:'250px'},{title:'User',value:'user',width:'125px'},{title:'Storage',value:'storage',width:'100px'},{title:'Notes',value:'notes'},{title:'',value:'actions',sortable: false,width:'190px'}]"
						:items="dialog_site.environment_selected.snapshots"
						item-key="snapshot_id"
						no-data-text="No snapshots found."
					>
					<template v-slot:item.user="{ item }">
						{{ item.user.name }}
					</template>
					<template v-slot:item.created_at="{ item }">
						{{ pretty_timestamp_epoch( item.created_at ) }}
					</template>
					<template v-slot:item.storage="{ item }">
						{{ formatSize( item.storage ) }}
					</template>
					<template v-slot:item.actions="{ item }">
					<template v-if="item.token && new Date() < new Date( item.expires_at )">
						<v-tooltip location="bottom">
							<template v-slot:activator="{ props }">
                    			<v-btn size="small" icon @click="fetchLink( dialog_site.site.site_id, item.snapshot_id )" v-bind="props">
								<v-icon color="grey">mdi-sync</v-icon>
							</v-btn>
							</template>
							<span>Generate new link. Link valid for 24hrs.</span>
						</v-tooltip>
                <v-btn size="small" rounded :href="`/wp-json/captaincore/v1/site/${dialog_site.site.site_id}/snapshots/${item.snapshot_id}-${item.token}/${item.snapshot_name.slice(0, -4)}`">Download</v-btn>
					</template>
					<template v-else>
						<v-tooltip location="bottom">
							<template v-slot:activator="{ props }">
                    <v-btn size="small" icon @click="fetchLink( dialog_site.site.site_id, item.snapshot_id )" v-bind="props">
								<v-icon color="grey">mdi-sync</v-icon>
							</v-btn>
							</template>
							<span>Generate new link. Link valid for 24hrs.</span>
						</v-tooltip>
						<v-btn size="small" rounded disabled>Download</v-btn>
					</template>
					</template>
					</v-data-table>
					</div>
					</v-sheet>
			</v-window-item>
		</v-window>
		<v-card flat v-else>
		<v-container fluid>
       		<div><span><v-progress-circular indeterminate color="primary" class="ma-2" size="24"></v-progress-circular></span></div>
		 </v-container>
		</v-card>
	  </v-window-item>
	  <v-window-item :key="2" value="tab-Modules" :transition="false" :reverse-transition="false" v-if="role == 'administrator'">
		<v-toolbar density="compact" light flat>
			<v-toolbar-title>Modules</v-toolbar-title>
			<v-spacer></v-spacer>
		</v-toolbar>
		<v-card flat>
			<v-card-text>
			<div v-for="environment in dialog_site.site.environments">
				{{ environment.environment }}
				<v-row class="ma-2">
					<v-col cols="6" md="3"><v-switch v-model="environment.monitor_enabled" label="Up-time Monitor" inset hide-details :false-value="0" :true-value="1" @change="toggleMonitor( environment )"></v-switch></v-col>
					<v-col cols="6" md="3">
						<v-switch v-model="environment.updates_enabled" label="Managed Updates" inset hide-details :false-value="0" :true-value="1" @change="toggleUpdates( environment )"></v-switch>
						<v-dialog max-width="600">
							<template v-slot:activator="{ props }">
								<v-btn size="small" variant="tonal" class="ml-12 mt-1" v-bind="props">Manage Exclusions</v-btn>
							</template>
							<template v-slot:default="{ isActive }">
							<v-card>
								<v-toolbar flat dark color="primary">
									<v-btn icon dark @click.native="isActive.value = false">
										<v-icon>mdi-close</v-icon>
									</v-btn>
									<v-toolbar-title>Update Exclusions</v-toolbar-title>
									<v-spacer></v-spacer>
								</v-toolbar>
								<v-card-text class="mt-3">
								<v-autocomplete
									:items="environment.plugins"
									:item-title="item => `${item.title} (${item.name})`"
									item-value="name"
									v-model="environment.updates_exclude_plugins"
									label="Plugins"
									multiple
									chips
									persistent-hint
								></v-autocomplete>
								<v-autocomplete
									:items="environment.themes"
									item-title="title"
									item-value="name"
									v-model="environment.updates_exclude_themes"
									label="Themes"
									multiple
									chips
									persistent-hint
								></v-autocomplete>
								<v-btn variant="tonal" color="primary" @click="isActive.value = false">Save</v-btn>
								</v-card-text>
							</v-card>
							</template>
						</v-dialog>
					</v-col>
				</v-row>
			</div>
			</v-card-text>
		</v-card>
		</v-window-item>
		<v-window-item :key="8" value="tab-Timeline" :transition="false" :reverse-transition="false">
			<v-toolbar density="compact" color="transparent" flat>
				<v-toolbar-title>Timeline</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-toolbar-items>
					<v-btn variant="text" @click="exportTimeline()">Export <v-icon dark>mdi-file-download</v-icon></v-btn>
					<a ref="export_json" href="#"></a>
				</v-toolbar-items>
			</v-toolbar>
			<v-card flat>
			<v-data-table
				:headers="header_timeline"
				:items="dialog_site.site.timeline"
				item-value="process_log_id"
				class="timeline"
			>
				<template v-slot:item="{ item }">
				<tr>
					<td class="justify-center pt-3 pr-0 text-center shrink" style="vertical-align: top">
					<v-tooltip location="bottom">
						<template v-slot:activator="{ props }">
						<v-icon
							v-if="item.name"
							color="primary"
							v-bind="props"
							icon="mdi-note"
						></v-icon>
						</template>
						<span>{{ item.name }}</span>
					</v-tooltip>
					<v-icon
						v-if="!item.name"
						color="primary"
						icon="mdi-checkbox-marked-circle"
					></v-icon>
					</td>
					<td class="justify-center py-4" style="vertical-align: top">
					<div v-if="item.description" v-html="item.description"></div>
					</td>
					<td class="pt-2" style="vertical-align: top;">
					<v-row align="center" no-gutters>
						<v-col cols="auto" class="pr-2">
							<v-img :src="item.author_avatar" width="34" class="rounded"></v-img>
						</v-col>
						<v-col>
							<div class="text-no-wrap">{{ item.author }}</div>
						</v-col>
					</v-row>
					</td>
					<td class="justify-center pt-3" style="vertical-align: top">
					{{ pretty_timestamp_epoch(item.created_at) }}
					</td>
					<td class="pt-1 pr-2" style="vertical-align: top">
					<v-btn
						v-if="role == 'administrator'"
						variant="text"
						icon
						@click="editLogEntry(dialog_site.site.site_id, item.process_log_id)"
					>
						<v-icon size="small" icon="mdi-pencil"></v-icon>
					</v-btn>
					</td>
				</tr>
				</template>
			</v-data-table>
			</v-data-table>
			</v-card>
		</v-window-item>
	</v-tabs>
	</v-container>
				</v-card>
			</v-sheet>
			<v-sheet v-show="dialog_site.step == 3" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Add Site</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-btn icon="mdi-close" @click="goToPath( `/sites` )"></v-btn>
				</v-toolbar>
				<v-card-text>
					<v-form ref="form" :disabled="dialog_new_site.saving">
						<v-row v-for="error in dialog_new_site.errors" :key="error">
							<v-col cols="12">
								<v-alert variant="tonal" type="error">
								{{ error }}
								</v-alert>
							</v-col>
						</v-row>
						<v-row>
							<v-col cols="12" md="6" class="py-1">
								<v-autocomplete
									:items='[{"name":"Kinsta","value":"kinsta"},{"name":"GridPane","value":"gridpane"},{"name":"Rocket.net","value":"rocketdotnet"},{"name":"WP Engine","value":"wpengine"}]'
									item-title="name"
									v-model="dialog_new_site.provider"
									label="Provider"
									variant="underlined"
								></v-autocomplete>
							</v-col>
							<v-col cols="12" md="6" class="py-1">
								<v-text-field :model-value="dialog_new_site.name" @update:model-value="dialog_new_site.name = $event" label="Domain name" required variant="underlined"></v-text-field>
							</v-col>
						</v-row>
						<v-row>
							<v-col cols="12" md="6" class="py-1">
								<v-text-field :model-value="dialog_new_site.site" @update:model-value="dialog_new_site.site = $event" label="Site name" required hint="Should match provider site name." persistent-hint variant="underlined"></v-text-field>
							</v-col>
							<v-col cols="12" md="6" class="py-1">
								<v-autocomplete
									:items="keySelections"
									v-model="dialog_new_site.key"
									item-title="title"
									item-value="key_id"
									label="Override SSH Key"
									hint="Will default to"
									persistent-hint
									chips
									closable-chips
									variant="underlined"
								>
									<template v-slot:message="{ message, key }">
										<span>{{ message }} <a :href="`${configurations.path}keys`" @click.prevent="goToPath( '/keys' )" >primary SSH key</a>.</span>
									</template>
								</v-autocomplete>
							</v-col>
						</v-row>
						<v-row v-show="configurations.mode == 'hosting'">
							<v-col cols="12">
								<v-autocomplete
									:items="accounts"
									v-model="dialog_new_site.shared_with"
									label="Assign to an account"
									item-title="name"
									item-value="account_id"
									chips
									closable-chips
									multiple
									return-object
									hint="If a customer account is not assigned then a new account will be created automatically."
									persistent-hint
									:menu-props="{ closeOnContentClick:true, openOnClick: false }"
									variant="underlined"
								>
								</v-autocomplete>
								<v-expand-transition>
									<v-row density="compact" v-if="dialog_new_site.shared_with && dialog_new_site.shared_with.length > 0" class="mt-3">
										<v-col v-for="account in dialog_new_site.shared_with" :key="account.account_id" cols="12" sm="6" md="4">
											<v-card>
												<v-card-title>{{ account.name }}</v-card-title>
												<v-card-actions>
													<v-tooltip location="top">
														<template v-slot:activator="{ props }">
															<v-btn-toggle v-model="dialog_new_site.customer_id" color="primary">
																<v-btn variant="text" :value="account.account_id" v-bind="props" icon="mdi-account-circle"></v-btn>
															</v-btn-toggle>
														</template>
														<span>Set as customer contact</span>
													</v-tooltip>
													<v-tooltip location="top">
														<template v-slot:activator="{ props }">
															<v-btn-toggle v-model="dialog_new_site.account_id" color="primary">
																<v-btn variant="text" :value="account.account_id" v-bind="props" icon="mdi-currency-usd"></v-btn>
															</v-btn-toggle>
														</template>
														<span>Set as billing contact</span>
													</v-tooltip>
												</v-card-actions>
											</v-card>
										</v-col>
									</v-row>
								</v-expand-transition>
							</v-col>
						</v-row>
						<v-row class="mt-5">
							<v-col cols="12" md="6" class="py-1" v-for="(key, index) in dialog_new_site.environments" :key="key.index">
								<v-toolbar flat density="compact" color="accent" class="pl-2">
									<div>{{ key.environment }} Environment</div>
									<v-spacer></v-spacer>
									<v-tooltip location="top" v-if="key.environment == 'Staging'">
										<template v-slot:activator="{ props }">
											<v-btn variant="text" size="small" icon="mdi-delete" color="red" @click="dialog_new_site.environments.splice( index, 1 )" v-bind="props"></v-btn>
										</template>
										<span>Delete Environment</span>
									</v-tooltip>
									<v-tooltip location="top" v-if="key.environment == 'Staging'">
										<template v-slot:activator="{ props }">
											<v-btn variant="text" size="small" icon="mdi-cached" color="green" @click="preloadStagingEnvironment(dialog_new_site)" v-bind="props"></v-btn>
										</template>
										<span>Preload based on Production</span>
									</v-tooltip>
								</v-toolbar>
								<v-text-field label="Address" :model-value="key.address" @update:model-value="key.address = $event" required hint="Server IP address or server host" persistent-hint variant="underlined"></v-text-field>
								<v-text-field label="Home Directory" :model-value="key.home_directory" @update:model-value="key.home_directory = $event" required variant="underlined"></v-text-field>
								<v-row dense>
									<v-col cols="6"><v-text-field label="Username" :model-value="key.username" @update:model-value="key.username = $event" required variant="underlined"></v-text-field></v-col>
									<v-col cols="6"><v-text-field label="Password" :model-value="key.password" @update:model-value="key.password = $event" required variant="underlined"></v-text-field></v-col>
								</v-row>
								<v-row dense>
									<v-col cols="6"><v-text-field label="Protocol" :model-value="key.protocol" @update:model-value="key.protocol = $event" required variant="underlined"></v-text-field></v-col>
									<v-col cols="6"><v-text-field label="Port" :model-value="key.port" @update:model-value="key.port = $event" required variant="underlined"></v-text-field></v-col>
								</v-row>
								<v-row dense>
									<v-col cols="6"><v-switch label="Automatic Updates" v-model="key.updates_enabled" false-value="0" true-value="1" color="primary" inset hide-details></v-switch></v-col>
									<v-col cols="6" v-if="typeof key.offload_enabled != 'undefined' && key.offload_enabled == 1">
										<v-switch label="Use Offload" v-model="key.offload_enabled" false-value="0" true-value="1" color="primary" inset hide-details></v-switch>
									</v-col>
								</v-row>
								<div v-if="key.offload_enabled == 1">
									<v-row dense>
										<v-col cols="6"><v-select label="Offload Provider" :model-value="key.offload_provider" @update:model-value="key.offload_provider = $event" :items='[{ provider:"s3", label: "Amazon S3" },{ provider:"do", label:"Digital Ocean" }]' item-title="label" item-value="provider" clearable variant="underlined"></v-select></v-col>
										<v-col cols="6"><v-text-field label="Offload Access Key" :model-value="key.offload_access_key" @update:model-value="key.offload_access_key = $event" required variant="underlined"></v-text-field></v-col>
									</v-row>
									<v-row dense>
										<v-col cols="6"><v-text-field label="Offload Secret Key" :model-value="key.offload_secret_key" @update:model-value="key.offload_secret_key = $event" required variant="underlined"></v-text-field></v-col>
										<v-col cols="6"><v-text-field label="Offload Bucket" :model-value="key.offload_bucket" @update:model-value="key.offload_bucket = $event" required variant="underlined"></v-text-field></v-col>
									</v-row>
									<v-row dense>
										<v-col cols="6"><v-text-field label="Offload Path" :model-value="key.offload_path" @update:model-value="key.offload_path = $event" required variant="underlined"></v-text-field></v-col>
									</v-row>
								</div>
							</v-col>
							<v-col cols="12" md="6" class="py-2" v-show="dialog_new_site.environments && dialog_new_site.environments.length == 1">
								<v-btn @click='dialog_new_site.environments.push( {"environment": "Staging", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"",monitor_enabled:"0",updates_enabled:"1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" } )' variant="outlined">Add Staging Environment</v-btn>
							</v-col>
						</v-row>
						<v-row>
							<v-col cols="6">
								<v-progress-circular v-show="dialog_new_site.saving" indeterminate color="primary" class="ma-2" size="24"></v-progress-circular>
							</v-col>
							<v-col cols="6" class="text-end">
								<v-dialog v-model="dialog_new_site.show_vars" scrollable max-width="700px">
									<template v-slot:activator="{ props }">
										<v-btn v-bind="props" class="mr-2" color="secondary" variant="tonal">Configure Environment Vars</v-btn>
									</template>
									<v-card>
										<v-list>
											<v-list-item>
												<v-list-item-title>Environment Vars</v-list-item-title>
												<v-list-item-subtitle>Pass along with SSH requests</v-list-item-subtitle>
												<template v-slot:append>
													<v-btn @click="addEnvironmentVarNewSite()">Add</v-btn>
												</template>
											</v-list-item>
										</v-list>
										<v-card-text>
											<v-row v-for="(item, index) in dialog_new_site.environment_vars" :key="index">
												<v-col class="pb-0">
													<v-text-field hide-details :model-value="item.key" @update:model-value="item.key = $event" label="Key" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="pb-0">
													<v-text-field hide-details :model-value="item.value" @update:model-value="item.value = $event" label="Value" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="pb-0 pt-5" style="max-width:58px">
													<v-btn icon="mdi-delete" @click="removeEnvironmentVarNewSite(index)" variant="text"></v-btn>
												</v-col>
											</v-row>
										</v-card-text>
										<v-card-actions>
											<v-spacer></v-spacer>
											<v-btn color="primary" variant="text" @click="dialog_new_site.show_vars = false">Close</v-btn>
										</v-card-actions>
									</v-card>
								</v-dialog>
								<v-btn color="primary" @click="submitNewSite()">Add Site</v-btn>
							</v-col>
						</v-row>
					</v-form>
				</v-card-text>
			</v-sheet>
			<v-sheet v-show="dialog_site.step == 4" color="transparent">
			<v-toolbar elevation="0" color="transparent">
				<v-toolbar-title>Edit Site {{ dialog_edit_site.site.name }}</v-toolbar-title>
				<v-spacer></v-spacer>
				<v-btn icon="mdi-close" @click="dialog_site.step = 2"></v-btn>
			</v-toolbar>
			<v-card-text v-if="role == 'administrator'">
				<v-form ref="form" :disabled="dialog_edit_site.loading">
				<v-row v-for="(error, index) in dialog_edit_site.errors" :key="index">
					<v-col cols="12">
					<v-alert variant="tonal" type="error" class="mb-2"> {{ error }} </v-alert>
					</v-col>
				</v-row>
				<v-row dense>
					<v-col cols="6">
					<v-autocomplete
						:items='[{"name":"Kinsta","value":"kinsta"},{"name":"GridPane","value":"gridpane"},{"name":"Rocket.net","value":"rocketdotnet"},{"name":"WP Engine","value":"wpengine"}]'
						item-title="name"
						item-value="value"
						v-model="dialog_edit_site.site.provider"
						label="Provider"
						variant="underlined"
					></v-autocomplete>
					</v-col>
					<v-col cols="6">
						<v-text-field v-model="dialog_edit_site.site.name" label="Domain name" required variant="underlined"></v-text-field>
					</v-col>
				</v-row>
				<v-row dense>
					<v-col cols="6">
						<v-text-field v-model="dialog_edit_site.site.site" label="Site name (not changeable)" disabled variant="underlined"></v-text-field>
					</v-col>
					<v-col cols="6">
					<v-autocomplete
						:items="keySelections"
						item-title="title"
						item-value="key_id"
						v-model="dialog_edit_site.site.key"
						label="Override SSH Key"
						hint="Will default to"
						variant="underlined"
						persistent-hint
						closable-chips
						chips
					>
						<template v-slot:message="{ message }">
						<span>{{ message }} <a :href="`${configurations.path}keys`" @click.prevent="goToPath( '/keys' )">primary SSH key</a>.</span>
						</template>
					</v-autocomplete>
					</v-col>
				</v-row>
				<v-row dense>
					<v-col cols="12" class="mx-2">
					<v-autocomplete
						:items="accounts"
						v-model="dialog_edit_site.site.shared_with"
						label="Assign to an account"
						item-title="name"
						item-value="account_id"
						chips
						closable-chips
						multiple
						return-object
						hint="If a customer account is not assigned then a new account will be created automatically."
						persistent-hint
						:menu-props="{ closeOnClick: false }"
						close-on-content-click
						variant="underlined"
					>
					</v-autocomplete>
					<v-expand-transition>
						<v-row density="compact" v-if="dialog_edit_site.site.shared_with && dialog_edit_site.site.shared_with.length > 0" class="mt-3">
						<v-col v-for="account in dialog_edit_site.site.shared_with" :key="account.account_id" cols="4">
							<v-card>
							<v-card-title v-html="account.name"></v-card-title>
							<v-card-actions>
								<v-tooltip location="top">
								<template v-slot:activator="{ props }">
									<v-btn-toggle v-model="dialog_edit_site.site.customer_id" color="primary" mandatory>
									<v-btn :value="account.account_id" v-bind="props" icon="mdi-account-circle"></v-btn>
									</v-btn-toggle>
								</template>
								<span>Set as customer contact</span>
								</v-tooltip>
								<v-tooltip location="top">
								<template v-slot:activator="{ props }">
									<v-btn-toggle v-model="dialog_edit_site.site.account_id" color="primary" mandatory>
									<v-btn :value="account.account_id" v-bind="props" icon="mdi-currency-usd"></v-btn>
									</v-btn-toggle>
								</template>
								<span>Set as billing contact</span>
								</v-tooltip>
							</v-card-actions>
							</v-card>
						</v-col>
						</v-row>
					</v-expand-transition>
					</v-col>
				</v-row>
				<v-row class="mt-5">
					<v-col cols="6" v-for="(key, index) in dialog_edit_site.site.environments" :key="key.environment">
					<v-toolbar elevation="0" density="compact" color="accent">
						<div>{{ key.environment }} Environment</div>
						<v-spacer></v-spacer>
						<v-tooltip location="top" v-if="key.environment == 'Staging'">
						<template v-slot:activator="{ props }">
							<v-btn variant="text" size="small" icon="mdi-delete" color="red" @click="dialog_edit_site.site.environments.splice( index, 1 )" v-bind="props"></v-btn>
						</template>
						<span>Delete Environment</span>
						</v-tooltip>
						<v-tooltip location="top" v-if="key.environment == 'Staging'">
						<template v-slot:activator="{ props }">
							<v-btn variant="text" size="small" icon="mdi-cached" color="green" @click="preloadStagingEnvironment(dialog_edit_site.site)"v-bind="props"></v-btn>
						</template>
						<span>Preload based on Production</span>
						</v-tooltip>
					</v-toolbar>
					<v-row dense>
						<v-col cols="12"><v-text-field label="Address" v-model="key.address" required variant="underlined"></v-text-field></v-col>
					</v-row>
					<v-row dense>
						<v-col cols="12"><v-text-field label="Home Directory" v-model="key.home_directory" required variant="underlined"></v-text-field></v-col>
					</v-row>
					<v-row dense>
						<v-col cols="6"><v-text-field label="Username" v-model="key.username" required variant="underlined"></v-text-field></v-col>
						<v-col cols="6"><v-text-field label="Password" v-model="key.password" required variant="underlined"></v-text-field></v-col>
					</v-row>
					<v-row dense>
						<v-col cols="6"><v-text-field label="Protocol" v-model="key.protocol" required variant="underlined"></v-text-field></v-col>
						<v-col cols="6"><v-text-field label="Port" v-model="key.port" required variant="underlined"></v-text-field></v-col>
					</v-row>
					<v-row dense>
						<v-col cols="6" v-if="typeof key.offload_enabled != 'undefined' && key.offload_enabled == 1">
							<v-switch label="Use Offload" v-model="key.offload_enabled" false-value="0" true-value="1" color="primary" inset></v-switch>
						</v-col>
					</v-row>
					<div v-if="key.offload_enabled == 1">
						<v-row>
						<v-col cols="6"><v-select label="Offload Provider" v-model="key.offload_provider" :items='[{ provider:"s3", label: "Amazon S3" },{ provider:"do", label:"Digital Ocean" }]' item-title="label" item-value="provider" clearable></v-select></v-col>
						<v-col cols="6"><v-text-field label="Offload Access Key" v-model="key.offload_access_key" required variant="underlined"></v-text-field></v-col>
						</v-row>
						<v-row>
						<v-col cols="6"><v-text-field label="Offload Secret Key" v-model="key.offload_secret_key" required variant="underlined"></v-text-field></v-col>
						<v-col cols="6"><v-text-field label="Offload Bucket" v-model="key.offload_bucket" required variant="underlined"></v-text-field></v-col>
						</v-row>
						<v-row>
						<v-col cols="6"><v-text-field label="Offload Path" v-model="key.offload_path" required variant="underlined"></v-text-field></v-col>
						</v-row>
					</div>
					</v-col>
					<v-col class="mx-2" cols="6" v-show="dialog_edit_site.site.environments && dialog_edit_site.site.environments.length == 1">
					<v-btn @click='dialog_edit_site.site.environments.push( {"environment": "Staging", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"",monitor_enabled: "0",updates_enabled: "1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" } )'>Add Staging Environment</v-btn>
					</v-col>
				</v-row>
				<v-row>
					<v-col cols="6"><v-progress-circular v-show="dialog_edit_site.loading" indeterminate color="primary"></v-progress-circular></v-col>
					<v-col cols="6" class="text-right">
					<v-dialog v-model="dialog_edit_site.show_vars" scrollable scrim="false" max-width="700px">
						<template v-slot:activator="{ props }">
						<v-btn v-bind="props" class="mr-2">Configure Environment Vars</v-btn>
						</template>
						<v-card>
						<v-list>
							<v-list-item>
							<div>
								<v-list-item-title>Environment Vars</v-list-item-title>
								<v-list-item-subtitle>Pass along with SSH requests</v-list-item-subtitle>
							</div>
							<template v-slot:append>
								<v-btn @click="addEnvironmentVar()">Add</v-btn>
							</template>
							</v-list-item>
						</v-list>
						<v-card-text>
							<v-row v-for="(item, index) in dialog_edit_site.site.environment_vars" :key="index">
							<v-col class="pb-0"><v-text-field hide-details v-model="item.key" label="Key" variant="underlined"></v-text-field></v-col>
							<v-col class="pb-0"><v-text-field hide-details v-model="item.value" label="Value" variant="underlined"></v-text-field></v-col>
							<v-col class="pb-0 pt-5" style="max-width:58px"><v-btn variant="text" icon="mdi-delete" @click="removeEnvironmentVar(index)"></v-btn></v-col>
							</v-row>
						</v-card-text>
						<v-card-actions>
							<v-spacer></v-spacer>
							<v-btn color="primary" variant="text" @click="dialog_edit_site.show_vars = false">Close</v-btn>
						</v-card-actions>
						</v-card>
					</v-dialog>
					<v-btn @click="updateSite" color="primary"> Save Changes </v-btn>
					</v-col>
				</v-row>
				</v-form>
			</v-card-text>
			</v-sheet>

			</v-card>
			<v-card v-if="route == 'domains'" flat border="thin" rounded="xl">
			<v-sheet v-show="dialog_domain.step == 1" color="transparent">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Listing {{ allDomains }} domains</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn variant="text" @click="dialog_new_domain.show = true">Add Domain <v-icon dark>mdi-plus</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text>
				<v-card flat class="mb-4 dns_introduction" v-show="configurations.dns_nameservers != ''">
					<v-alert type="info" variant="tonal">
						<v-row class="flex-nowrap">
						<v-col class="flex-grow-1 flex-shrink-0">
							<div v-html="configurations.dns_introduction_html"></div>
						</v-col>
						<v-col class="flex-shrink-1 flex-grow-0">
						<v-dialog max-width="440">
							<template v-slot:activator="{ props }">
								<v-btn v-bind="props" color="primary">Show Nameservers</v-btn>
							</template>
							<template v-slot:default="{ isActive }">
							<v-card>
								<v-toolbar color="primary">
								<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
								<v-toolbar-title>Nameservers</v-toolbar-title>
								<v-spacer></v-spacer>
								</v-toolbar>
								<v-list-item v-for="nameserver in configurations.dns_nameservers.split('\n')" @click="copyText( nameserver )" link>
								<v-list-item-title>{{ nameserver }}</v-list-item-title>
								<template v-slot:append>
									<v-icon>mdi-content-copy</v-icon>
								</template>
								</v-list-item>
							</v-card>
							</template>
						</v-dialog>
						</v-col>
						</v-row>
					</v-alert>
				</v-card>
				<v-row class="my-2" justify="end">
					<v-col cols="12" md="4" lg="3">
						<v-text-field 
							v-model="domain_search" 
							append-inner-icon="mdi-magnify" 
							label="Search" 
							density="compact" 
							variant="outlined" 
							clearable 
							autofocus 
							hide-details 
							flat
						></v-text-field>
					</v-col>
				</v-row>
				<v-data-table
					:headers="[{ title: 'Name', value: 'name' },{ title: 'DNS', value: 'remote_id', width: '88px' },{ title: 'Registration', value: 'provider_id', width: '120px' }]"
					:items="domains"
					:search="domain_search"
					:items-per-page="100"
					:items-per-page-options="[100,250,500,{'title':'All','value':-1}]"
					item-value="domain_id"
					hover
					density="comfortable"
					@click:row="(event, { item }) => goToPath(`/domains/${item.domain_id}`)"
					style="cursor:pointer"
				>
					<template v-slot:item.remote_id="{ value }">
						<v-icon v-if="value != null && value !== ''" icon="mdi-check-circle"></v-icon>
					</template>
					<template v-slot:item.provider_id="{ value }">
						<v-icon v-if="value != null && value !== ''" icon="mdi-check-circle"></v-icon>
					</template>
				</v-data-table>
				</v-card-text>
				</v-sheet>
				<v-sheet v-show="dialog_domain.step == 2" color="transparent">
					<div v-if="dialog_domain.loading" class="text-center pa-10">
						<v-progress-circular indeterminate color="primary"></v-progress-circular>
					</div>
					<v-card v-else flat rounded="xl">
						<v-toolbar flat color="transparent">
							<v-toolbar-title>
							<v-autocomplete
								v-model="dialog_domain.domain"
								:items="domains"
								return-object
								item-title="name"
								@update:model-value="(event) => goToPath( `/domains/${dialog_domain.domain.domain_id}`)"
								class="mt-5"
								spellcheck="false"
								density="compact"
								variant="outlined"
								flat
								style="max-width: 300px;"
							></v-autocomplete>
							</v-toolbar-title>
							<v-spacer></v-spacer>
						</v-toolbar>
						<v-container class="pt-0">
						<v-toolbar color="primary" dark flat density="compact" rounded="lg">
						<v-tabs v-model="dialog_domain.tabs" density="compact" mandatory hide-slider>
							<v-tab value="dns">
								DNS Records <v-icon class="ml-1" icon="mdi-table"></v-icon>
							</v-tab>
							<v-tab value="domain">
								Domain Management <v-icon class="ml-1" icon="mdi-account-box"></v-icon>
							</v-tab>
							<v-tab value="email-forwarding" v-if="dialog_domain.details.forward_email_id" @click="fetchEmailForwards">
								Email Forwarding <v-icon class="ml-1" icon="mdi-email-arrow-right"></v-icon>
							</v-tab>
							<v-tab value="mailgun" v-if="dialog_domain.details.mailgun_id">
								Mailgun <v-icon class="ml-1" icon="mdi-email"></v-icon>
							</v-tab>
						</v-tabs>
						</v-toolbar>
						<v-window v-model="dialog_domain.tabs">
							<v-window-item value="dns" :transition="false" :reverse-transition="false">
							<v-toolbar flat density="compact" color="transparent" v-if="dialog_domain.domain.remote_id">
								<v-toolbar-title v-show="dnsRecords > 0"><small>{{ dnsRecords }} DNS records</small></v-toolbar-title>
								<v-spacer></v-spacer>
								<v-toolbar-items>
								<v-dialog max-width="800" v-model="dialog_domain.show_import">
									<v-card>
										<v-toolbar color="primary">
											<v-btn icon="mdi-close" @click="dialog_domain.show_import = false"></v-btn>
										<v-toolbar-title>Import DNS Records</v-toolbar-title>
										<v-spacer></v-spacer>
										</v-toolbar>
										<v-card-text class="mt-5">
										<v-textarea 
											placeholder="Paste DNS zone file" 
											variant="outlined"
											persistent-hint 
											hint="Paste DNS zone file then click import DNS records. Records changes will be shown in editor for confirmation." 
											v-model="dialog_domain.import_json"
											spellcheck="false">
										</v-textarea>
										</v-card-text>
										<v-card-actions class="justify-end">
											<v-btn variant="tonal" class="ma-0" @click="loadDNSRecords()">Load DNS Records</v-btn>
										</v-card-actions>
									</v-card>
								</v-dialog>
									<v-btn variant="text" @click="dialog_domain.show_import = true">Import <v-icon icon="mdi-file-upload" class="ml-1"></v-icon></v-btn>
									<v-btn variant="text" @click="exportDomain()">Export <v-icon icon="mdi-file-download" class="ml-1"></v-icon></v-btn>
								</v-toolbar-items>
							</v-toolbar>
								<v-row v-if="dialog_domain.errors && dialog_domain.errors.length > 0">
									<v-col class="ma-3">
										<v-alert variant="tonal" type="error" v-for="error in dialog_domain.errors">{{ error }}</v-alert>
									</v-col>
								</v-row>
								<v-row v-if="dialog_domain.info && dialog_domain.info.length > 0">
									<v-col class="ma-3">
										<v-alert variant="tonal" type="info" color="primary">
											<div v-for="info in dialog_domain.info">{{ info }}</div>
											<v-btn color="primary" @click="activateDNSZone(dialog_domain.domain)" :loading="dialog_domain.deleting_dns_zone" class="mt-3" v-if="!dialog_domain.domain.remote_id">
												Activate DNS Zone
											</v-btn>
										</v-alert>
									</v-col>
								</v-row>
								<v-row>
									<v-col>
										<v-progress-circular indeterminate color="primary" size="24" class="ma-5" v-show="dialog_domain.loading"></v-progress-circular>
										<div class="v-table v-table--has-top v-table--has-bottom v-table--density-default v-data-table">
										<div class="v-table__wrapper">
										<table class="table-dns mb-3" v-show="dialog_domain.records.length > 0">
											<thead class="v-data-table-header">
											<tr class="v-data-table__td v-data-table-column--align-start v-data-table__th">
												<th width="175">Type</th>
												<th width="200">Name</th>
												<th>Value</th>
												<th width="75">TTL</th>
												<th width="95"></th>
											</tr>
											</thead>
											<tbody>
											<tr v-for="(record, index) in dialog_domain.records" :key="record.id" v-bind:class="{ new: record.new, edit: record.edit, delete: record.delete }" class="v-data-table__tr">
											<template v-if="record.edit">
											<td class="pt-3">{{ record.type }}</td>
											<td><v-text-field variant="underlined" label="Name" :model-value="record.update.record_name" @update:model-value="record.update.record_name = $event" :disabled="dialog_domain.saving"></v-text-field></td>
											<td class="value" v-if="record.type == 'MX'">
												<v-row v-for="(value, value_index) in record.update.record_value" :key="`mx-edit-${index}-${value_index}`" no-gutters>
													<v-col cols="3"><v-text-field variant="underlined" hide-details label="Priority" :model-value="value.priority" @update:model-value="value.priority = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
													<v-col cols="9"><v-text-field variant="underlined" hide-details label="Server" :model-value="value.server" @update:model-value="value.server = $event" :disabled="dialog_domain.saving">
														<template v-slot:append-inner><v-btn variant="text" icon="mdi-delete" color="primary" class="ma-0 pa-0" @click="deleteRecordValue( index, value_index )" :disabled="dialog_domain.saving"></v-btn></template></v-text-field>
													</v-col>
												</v-row>
												<v-btn icon="mdi-plus-box" variant="text" density="compact" color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving"></v-btn>
											</td>
											<td class="value" v-else-if="record.type == 'A' || record.type == 'AAAA' || record.type == 'ANAME' || record.type == 'TXT' || record.type == 'CNAME' || record.type == 'SPF'">
												<div v-for="(value, value_index) in record.update.record_value" :key="`value-edit-${index}-${value_index}`">
													<v-text-field variant="underlined" hide-details label="Value" :model-value="value.value" @update:model-value="value.value = $event" :disabled="dialog_domain.saving">
														<template v-slot:append-inner><v-btn variant="text" icon="mdi-delete" color="primary" @click="deleteRecordValue( index, value_index )" :disabled="dialog_domain.saving" v-show="record.type != 'CNAME'"></v-btn></template>
													</v-text-field>
												</div>
												<v-btn icon="mdi-plus-box" variant="text" density="compact" color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving && record.type != 'CNAME'"></v-btn>
											</td>
											<td class="value" v-else-if="record.type == 'HTTP'">
												<v-text-field variant="underlined" label="Value" :model-value="record.update.record_value.url" @update:model-value="record.update.record_value.url = $event" :disabled="dialog_domain.saving"></v-text-field>
											</td>
											<td class="value" v-else-if="record.type == 'SRV'">
												<v-row v-for="(value, value_index) in record.update.record_value" :key="`srv-edit-${index}-${value_index}`">
												<v-col cols="2"><v-text-field variant="underlined" label="Priority" :model-value="value.priority" @update:model-value="value.priority = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
												<v-col cols="2"><v-text-field variant="underlined" label="Weight" :model-value="value.weight" @update:model-value="value.weight = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
												<v-col cols="2"><v-text-field variant="underlined" label="Port" :model-value="value.port" @update:model-value="value.port = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
												<v-col cols="6"><v-text-field variant="underlined" label="Host" :model-value="value.host" @update:model-value="value.host = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
												</v-row>
												<v-btn icon="mdi-plus-box" variant="text" density="compact" color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving"></v-btn>
											</td>
											<td class="value" v-else>
												<v-text-field variant="underlined" label="Value" :model-value="record.update.record_value" @update:model-value="record.update.record_value = $event" :disabled="dialog_domain.saving"></v-text-field>
											</td>
											<td><v-text-field variant="underlined" label="TTL" :model-value="record.update.record_ttl" @update:model-value="record.update.record_ttl = $event" :disabled="dialog_domain.saving"></v-text-field></td>
											<td class="text-right pt-3">
												<v-btn variant="text" color="primary" density="compact" @click="viewRecord( record.id )" :disabled="dialog_domain.saving" icon="mdi-pencil-box"></v-btn>
												<v-btn variant="text" color="primary" density="compact" @click="deleteRecord( record.id )" :disabled="dialog_domain.saving" icon="mdi-delete"></v-btn>
											</td>
											</template>
											<template v-else-if="record.new">
											<td><v-select variant="underlined" v-model="record.type" @update:model-value="changeRecordType( index )" item-title="name" item-value="value" :items='[{"name":"A","value":"A"},{"name":"AAAA","value":"AAAA"},{"name":"ANAME","value":"ANAME"},{"name":"CNAME","value":"CNAME"},{"name":"HTTP Redirect","value":"HTTP"},{"name":"MX","value":"MX"},{"name":"SRV","value":"SRV"},{"name":"TXT","value":"TXT"}]' label="Type" :disabled="dialog_domain.saving"></v-select></td>
											<td><v-text-field variant="underlined" label="Name" :model-value="record.update.record_name" @update:model-value="record.update.record_name = $event" :disabled="dialog_domain.saving"></v-text-field></td>
											<td class="value" v-if="record.type == 'MX'">
												<v-row v-for="(value, value_index) in record.update.record_value" :key="`mx-new-${index}-${value_index}`">
												<v-col cols="3"><v-text-field variant="underlined" hide-details label="Priority" :model-value="value.priority" @update:model-value="value.priority = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
												<v-col cols="9"><v-text-field variant="underlined" hide-details label="Server" :model-value="value.server" @update:model-value="value.server = $event" :disabled="dialog_domain.saving"><template v-slot:append-inner><v-btn variant="text" icon="mdi-delete" color="primary" density="compact" size="small" @click="deleteRecordValue( index, value_index )" :disabled="dialog_domain.saving"></v-btn></template></v-text-field></v-col>
												</v-row>
												<v-btn icon="mdi-plus-box" variant="text" color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving"></v-btn>
											</td>
											<td class="value" v-else-if="record.type == 'A' || record.type == 'AAAA' || record.type == 'ANAME' || record.type == 'CNAME' || record.type == 'TXT' || record.type == 'SPF'">
												<div v-for="(value, value_index) in record.update.record_value" :key="`value-new-${index}-${value_index}`">
													<v-text-field variant="underlined" hide-details label="Value" :model-value="record.update.record_value[value_index].value" @update:model-value="record.update.record_value[value_index].value = $event" :disabled="dialog_domain.saving">
														<template v-slot:append-inner><v-btn variant="text" icon="mdi-delete" color="primary" class="ma-0 pa-0" @click="deleteRecordValue( index, value_index )" :disabled="dialog_domain.saving" v-show="record.type != 'CNAME'"></v-btn></template>
													</v-text-field>
												</div>
												<v-btn icon="mdi-plus-box" variant="text" density="compact" color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving && record.type != 'CNAME'"></v-btn>
											</td>
											<td class="value" v-else-if="record.type == 'HTTP'">
												<v-text-field variant="underlined" label="Value" :model-value="record.update.record_value.url" @update:model-value="record.update.record_value.url = $event" :disabled="dialog_domain.saving"></v-text-field>
											</td>
											<td class="value" v-else-if="record.type == 'SRV'">
												<v-row v-for="(value, value_index) in record.update.record_value" :key="`srv-new-${index}-${value_index}`">
												<v-col cols="2"><v-text-field variant="underlined" label="Priority" :model-value="value.priority" @update:model-value="value.priority = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
												<v-col cols="2"><v-text-field variant="underlined" label="Weight" :model-value="value.weight" @update:model-value="value.weight = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
												<v-col cols="2"><v-text-field variant="underlined" label="Port" :model-value="value.port" @update:model-value="value.port = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
												<v-col cols="6"><v-text-field variant="underlined" label="Host" :model-value="value.host" @update:model-value="value.host = $event" :disabled="dialog_domain.saving"></v-text-field></v-col>
												</v-row>
												<v-btn icon="mdi-plus-box" variant="text" density="compact" color="primary" class="ma-0 mb-3" @click="addRecordValue( index )" v-show="!dialog_domain.loading && !dialog_domain.saving"></v-btn>
											</td>
											<td class="value" v-else>
												<v-text-field variant="underlined" label="Value" :model-value="record.update.record_value" @update:model-value="record.update.record_value = $event" :disabled="dialog_domain.saving"></v-text-field>
											</td>
											<td><v-text-field variant="underlined" label="TTL" :model-value="record.update.record_ttl" @update:model-value="record.update.record_ttl = $event" :disabled="dialog_domain.saving"></v-text-field></td>
											<td class="text-right pt-3">
												<v-btn variant="text" icon="mdi-delete" color="primary" density="compact" @click="deleteRecord( index )" :disabled="dialog_domain.saving"></v-btn>
											</td>
											</template>
											<template v-else>
												<td>{{ record.type }}</td>
												<td class="name">{{ record.name }}</td>
												<td class="value" v-if="record.type == 'MX'"><div v-for="value in record.value">{{ value.priority }} {{ value.server }}</div></td>
												<td class="value" v-else-if="record.type == 'A' || record.type == 'AAAA' || record.type == 'ANAME' || record.type == 'CNAME' || record.type == 'TXT'"><div v-for="item in record.value">{{ item.value }}</div></td>
												<td class="value" v-else-if="record.type == 'TXT'"><div v-for="item in record.value">{{ item.value.value }}</div></td>
												<td class="value" v-else-if="record.type == 'SRV'"><div v-for="value in record.value">{{ value.priority }} {{ value.weight }} {{ value.port }} {{ value.host }}</div></td>
												<td class="value" v-else-if="record.type == 'HTTP'">{{ record.value.url }}</td>
												<td class="value" v-else>{{ record.value.value }}</td>
												<td>{{ record.ttl }}</td>
												<td class="text-right">
													<v-btn variant="text" icon="mdi-pencil" color="primary" density="compact" @click="editRecord( record.id )" :disabled="dialog_domain.saving"></v-btn>
													<v-btn variant="text" icon="mdi-delete" color="primary" density="compact" @click="deleteCurrentRecord( record.id )" :disabled="dialog_domain.saving"></v-btn>
												</td>
											</template>
											</tr>
										</tbody>
										</table>
										</div>
										</div>
										<v-btn variant="tonal" class="ml-4" @click="addRecord()" v-show="!dialog_domain.loading && !dialog_domain.saving && dialog_domain.domain.remote_id">Add Additional Record</v-btn>
									</v-col>
								</v-row>
								<v-row v-show="dialog_domain.saving">
									<v-col>
										<v-progress-circular indeterminate color="primary" size="24" class="ml-4"></v-progress-circular>
									</v-col>
								</v-row>
								<div v-show="dialog_domain.results">
									<template v-for="result in dialog_domain.results">
										<v-alert type="success" v-show="typeof result.success != 'undefined'" v-html="result.success" class="text-body-2 ma-4"></v-alert>
										<v-alert type="error" v-if="typeof result.errors != 'undefined'" class="text-body-2 ma-4">{{ result.errors }}</v-alert>
									</template>
								</div>
								<v-row>
									<v-col class="text-left mx-3 mb-7" v-show="!dialog_domain.loading && dialog_domain.domain.remote_id">
										<v-btn class="mx-1" color="primary" @click="saveDNS()" :dark="dialog_domain.records && dialog_domain.records.length != '0'" :disabled="dialog_domain.records && dialog_domain.records.length == '0'">Save Records</v-btn>
										<a ref="export_domain" href="#"></a>
									</v-col>
								</v-row>
							</v-window-item>
							<v-window-item value="domain" :transition="false" :reverse-transition="false">
							<v-col v-show="!dialog_domain.provider.domain">
								<v-alert type="info" color="primary" variant="tonal">Domain is registered through another provider.</v-alert>
							</v-col>
							<div v-show="dialog_domain.provider.domain">
								<v-card flat>
									<v-overlay :model-value="dialog_domain.updating_contacts" class="align-center justify-center" contained>
										<v-progress-circular indeterminate size="64"></v-progress-circular>
									</v-overlay>
									<v-tabs v-model="dialog_domain.contact_tabs" @update:model-value="populateStatesforContacts()">
										<v-tab value="owner">Owner</v-tab>
										<v-tab value="admin">Admin</v-tab>
										<v-tab value="technical">Technical</v-tab>
										<v-tab value="billing">Billing</v-tab>
									</v-tabs>
									<v-window v-model="dialog_domain.contact_tabs" class="mt-2">
										<v-window-item value="owner" v-if="dialog_domain.provider.contacts.owner">
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="First Name" v-model="dialog_domain.provider.contacts.owner.first_name" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Last Name" v-model="dialog_domain.provider.contacts.owner.last_name" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Company name (optional)" v-model="dialog_domain.provider.contacts.owner.org_name" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Street Address" persistent-hint hint="House number and street name" v-model="dialog_domain.provider.contacts.owner.address1" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="" persistent-hint hint="Apartment, suite, unit, etc. (optional)" v-model="dialog_domain.provider.contacts.owner.address2" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Town" v-model="dialog_domain.provider.contacts.owner.city" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-autocomplete label="State" v-model="dialog_domain.provider.contacts.owner.state" :items="states_selected" variant="underlined" v-if="states_selected.length > 0"></v-autocomplete>
													<v-text-field label="State" v-model="dialog_domain.provider.contacts.owner.state" variant="underlined" v-else></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Zip" v-model="dialog_domain.provider.contacts.owner.postal_code" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-autocomplete label="Country" v-model="dialog_domain.provider.contacts.owner.country" :items="countries" @update:model-value="populateStatesFor( dialog_domain.provider.contacts.owner )" variant="underlined"></v-autocomplete>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Phone" v-model="dialog_domain.provider.contacts.owner.phone" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Email" v-model="dialog_domain.provider.contacts.owner.email" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
										</v-window-item>
										<v-window-item value="admin" v-if="dialog_domain.provider.contacts.admin">
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="First Name" v-model="dialog_domain.provider.contacts.admin.first_name" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Last Name" v-model="dialog_domain.provider.contacts.admin.last_name" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Company name (optional)" v-model="dialog_domain.provider.contacts.admin.org_name" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Street Address" persistent-hint hint="House number and street name" v-model="dialog_domain.provider.contacts.admin.address1" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="" persistent-hint hint="Apartment, suite, unit, etc. (optional)" v-model="dialog_domain.provider.contacts.admin.address2" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Town" v-model="dialog_domain.provider.contacts.admin.city" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-autocomplete label="State" v-model="dialog_domain.provider.contacts.admin.state" :items="states_selected" variant="underlined" v-if="states_selected.length > 0"></v-autocomplete>
													<v-text-field label="State" v-model="dialog_domain.provider.contacts.admin.state" variant="underlined" v-else></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Zip" v-model="dialog_domain.provider.contacts.admin.postal_code" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-autocomplete label="Country" v-model="dialog_domain.provider.contacts.admin.country" :items="countries" @update:model-value="populateStatesFor( dialog_domain.provider.contacts.admin )" variant="underlined"></v-autocomplete>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Phone" v-model="dialog_domain.provider.contacts.admin.phone" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Email" v-model="dialog_domain.provider.contacts.admin.email" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
										</v-window-item>
										<v-window-item value="technical" v-if="dialog_domain.provider.contacts.tech">
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="First Name" v-model="dialog_domain.provider.contacts.tech.first_name" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Last Name" v-model="dialog_domain.provider.contacts.tech.last_name" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Company name (optional)" v-model="dialog_domain.provider.contacts.tech.org_name" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Street Address" persistent-hint hint="House number and street name" v-model="dialog_domain.provider.contacts.tech.address1" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="" persistent-hint hint="Apartment, suite, unit, etc. (optional)" v-model="dialog_domain.provider.contacts.tech.address2" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Town" v-model="dialog_domain.provider.contacts.tech.city" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-autocomplete label="State" v-model="dialog_domain.provider.contacts.tech.state" :items="states_selected" variant="underlined" v-if="states_selected.length > 0"></v-autocomplete>
													<v-text-field label="State" v-model="dialog_domain.provider.contacts.tech.state" variant="underlined" v-else></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Zip" v-model="dialog_domain.provider.contacts.tech.postal_code" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-autocomplete label="Country" v-model="dialog_domain.provider.contacts.tech.country" :items="countries" @update:model-value="populateStatesFor( dialog_domain.provider.contacts.tech )" variant="underlined"></v-autocomplete>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Phone" v-model="dialog_domain.provider.contacts.tech.phone" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Email" v-model="dialog_domain.provider.contacts.tech.email" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
										</v-window-item>
										<v-window-item value="billing" v-if="dialog_domain.provider.contacts.billing">
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="First Name" v-model="dialog_domain.provider.contacts.billing.first_name" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Last Name" v-model="dialog_domain.provider.contacts.billing.last_name" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Company name (optional)" v-model="dialog_domain.provider.contacts.billing.org_name" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Street Address" persistent-hint hint="House number and street name" v-model="dialog_domain.provider.contacts.billing.address1" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="" persistent-hint hint="Apartment, suite, unit, etc. (optional)" v-model="dialog_domain.provider.contacts.billing.address2" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Town" v-model="dialog_domain.provider.contacts.billing.city" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-autocomplete label="State" v-model="dialog_domain.provider.contacts.billing.state" :items="states_selected" variant="underlined" v-if="states_selected.length > 0"></v-autocomplete>
													<v-text-field label="State" v-model="dialog_domain.provider.contacts.billing.state" variant="underlined" v-else></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Zip" v-model="dialog_domain.provider.contacts.billing.postal_code" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-autocomplete label="Country" v-model="dialog_domain.provider.contacts.billing.country" :items="countries" @update:model-value="populateStatesFor( dialog_domain.provider.contacts.billing )" variant="underlined"></v-autocomplete>
												</v-col>
											</v-row>
											<v-row no-gutters class="mx-3">
												<v-col class="ma-1">
													<v-text-field label="Phone" v-model="dialog_domain.provider.contacts.billing.phone" variant="underlined"></v-text-field>
												</v-col>
												<v-col class="ma-1">
													<v-text-field label="Email" v-model="dialog_domain.provider.contacts.billing.email" variant="underlined"></v-text-field>
												</v-col>
											</v-row>
										</v-window-item>
									</v-window>
									<v-row class="mx-2 mb-5">
										<v-col cols="12">
											<v-btn @click="updateDomainContacts()" color="primary">
												Update Contact Information
											</v-btn>
										</v-col>
									</v-row>
								</v-card>
								<v-divider></v-divider>
								<v-list-subheader>Nameservers</v-list-subheader>
								<v-card flat>
									<v-overlay :model-value="dialog_domain.updating_nameservers" class="align-center justify-center" contained>
										<v-progress-circular indeterminate size="64"></v-progress-circular>
									</v-overlay>
									<v-row no-gutters class="mx-3" v-for="(nameserver, index) in dialog_domain.provider.nameservers" :key="index">
										<v-col class="ma-1">
											<v-text-field v-model="nameserver.value" hide-details spellcheck="false" variant="underlined"></v-text-field>
										</v-col>
										<v-col class="mt-1">
											<v-btn variant="text" size="small" icon color="primary" class="ma-3" @click="dialog_domain.provider.nameservers.splice(index, 1)">
												<v-icon>mdi-delete</v-icon>
											</v-btn>
										</v-col>
									</v-row>
									<v-row class="mx-2">
										<v-col cols="12">
											<v-btn variant="tonal" @click="dialog_domain.provider.nameservers.push({ value: '' })">Add Additional Nameserver</v-btn>
										</v-col>
									</v-row>
									<v-row class="mx-2 mb-5">
										<v-col cols="12">
											<v-btn @click="updateDomainNameservers()" color="primary">
												Update Nameservers
											</v-btn>
										</v-col>
									</v-row>
								</v-card>
								<v-divider></v-divider>
								<v-list-subheader>Controls</v-list-subheader>
								<v-container>
									<v-row>
										<v-col v-if="dialog_domain.auth_code != ''">
											<v-list-item @click="copyText(dialog_domain.auth_code)" density="compact" lines="two">
												<template v-slot:prepend>
													<v-icon>mdi-content-copy</v-icon>
												</template>
												<v-list-item-title>Auth Code</v-list-item-title>
												<v-list-item-subtitle v-text="dialog_domain.auth_code"></v-list-item-subtitle>
											</v-list-item>
										</v-col>
										<v-col v-else>
											<v-btn class="mx-1" variant="tonal" @click="retrieveAuthCode()" :loading="dialog_domain.fetch_auth_code">Retrieve Auth Code</v-btn>
										</v-col>
										<v-col>
											<v-switch v-model="dialog_domain.provider.locked" :loading="dialog_domain.update_lock" :disabled="dialog_domain.update_lock" false-value="off" true-value="on" :label="`Lock is ${dialog_domain.provider.locked}`" @update:model-value="domainLockUpdate()"></v-switch>
										</v-col>
										<v-col>
											<v-switch v-model="dialog_domain.provider.whois_privacy" :loading="dialog_domain.update_privacy" :disabled="dialog_domain.update_privacy" false-value="off" true-value="on" :label="`Privacy is ${dialog_domain.provider.whois_privacy}`" @update:model-value="domainPrivacyUpdate()"></v-switch>
										</v-col>
									</v-row>
								</v-container>
							</div>
						</v-window-item>
						<v-window-item value="email-forwarding" :transition="false" :reverse-transition="false">

								<v-alert
									v-if="!dialog_domain.forwards_domain.loading && dialog_domain.forwards_domain.data && (!dialog_domain.forwards_domain.data.has_mx_record || !dialog_domain.forwards_domain.data.has_txt_record)"
									type="info"
									variant="tonal"
									class="ma-4"
									border="start"
								>
									<v-alert-title>Domain Not Yet Verified</v-alert-title>
									<p>Your domain's DNS records have not yet been verified by Forward Email. Please ensure the following records are correctly added to your domain's DNS settings.</p>
									
									<v-list density="compact" bg-color="transparent" class="mt-2">
										<v-list-item prepend-icon="mdi-table" title="MX Record (Priority 10)">
											<v-list-item-subtitle>mx1.forwardemail.net</v-list-item-subtitle>
										</v-list-item>
										<v-list-item prepend-icon="mdi-table" title="MX Record (Priority 10)">
											<v-list-item-subtitle>mx2.forwardemail.net</v-list-item-subtitle>
										</v-list-item>
										<v-list-item prepend-icon="mdi-table" title="TXT Record (@)">
											<v-list-item-subtitle>forward-email-site-verification={{ dialog_domain.forwards_domain.data.verification_record }}</v-list-item-subtitle>
										</v-list-item>
									</v-list>

									<v-btn
										color="primary"
										@click="verifyForwardEmailDns(dialog_domain.domain.domain_id)"
										:loading="dialog_domain.forwards_domain.loading"
										class="mt-2"
									>
										Verify DNS Records
									</v-btn>
								</v-alert>

								<v-toolbar flat density="compact" color="transparent">
									<v-toolbar-title v-show="!dialog_domain.forwards.loading"><small>{{ dialog_domain.forwards.items.length }} email forwards</small></v-toolbar-title>
									<v-spacer></v-spacer>
									<v-toolbar-items>
										<v-btn variant="text" @click="addEmailForward()" :disabled="dialog_domain.forwards.loading">Add Forward <v-icon icon="mdi-plus" class="ml-1"></v-icon></v-btn>
									</v-toolbar-items>
								</v-toolbar>
								
								<v-data-table
									:loading="dialog_domain.forwards.loading"
									:disabled="dialog_domain.forwards.loading"
									:headers="[
										{ title: 'Alias (Prefix)', key: 'name', width: '200px' },
										{ title: 'Forwarding To (Recipients)', key: 'recipients_string' },
										{ title: 'Enabled', key: 'is_enabled', width: '100px' },
										{ title: 'Actions', key: 'actions', width: '120px', align: 'end', sortable: false }
									]"
									:items="dialog_domain.forwards.items"
									:items-per-page="100"
									:items-per-page-options="[100,250,500,{'title':'All','value':-1}]"
									item-value="id"
									density="comfortable"
								>
									<template v-slot:item.name="{ item }">
										<code>{{ item.name }}</code>
									</template>
									<template v-slot:item.is_enabled="{ item }">
										<v-switch
											v-model="item.is_enabled"
											@update:model-value="inlineUpdateEmailForward(item, $event)"
											color="primary"
											inset
											hide-details
											density="compact"
											:loading="dialog_domain.forwards.loading"
											:disabled="dialog_domain.forwards.loading"
										></v-switch>
									</template>
									<template v-slot:item.actions="{ item }">
										<v-btn variant="text" icon="mdi-pencil" color="primary" density="compact" @click="editEmailForward(item)" :disabled="dialog_domain.forwards.loading"></v-btn>
										<v-btn variant="text" icon="mdi-delete" color="error" density="compact" @click="deleteEmailForward(item)" :disabled="dialog_domain.forwards.loading"></v-btn>
									</template>
								</v-data-table>

								<v-dialog v-model="dialog_domain.forwards.show_dialog" max-width="600px" persistent>
									<v-card>
										<v-toolbar color="primary" flat>
											<v-toolbar-title>{{ dialog_domain.forwards.edited_index > -1 ? 'Edit' : 'Add' }} Email Forward</v-toolbar-title>
										</v-toolbar>
										<v-card-text>
											<v-container>
												<v-text-field
													v-model="dialog_domain.forwards.edited_item.name"
													label="Alias (Prefix)"
													:suffix="'@' + dialog_domain.domain.name"
													variant="underlined"
													hint="The part before the @. Use '*' for a catch-all."
													persistent-hint
												></v-text-field>
												<v-textarea
													v-model="dialog_domain.forwards.edited_item.recipients_string"
													label="Forwarding To (Recipients)"
													variant="underlined"
													hint="Comma-separated email addresses."
													persistent-hint
													rows="3"
													auto-grow
													class="mt-4"
												></v-textarea>
												<v-switch
													v-model="dialog_domain.forwards.edited_item.is_enabled"
													label="Enabled"
													color="primary"
													inset
													hide-details
													class="mt-4"
												></v-switch>
											</v-container>
										</v-card-text>
										<v-card-actions>
											<v-spacer></v-spacer>
											<v-btn variant="text" @click="closeEmailForwardDialog()">Cancel</v-btn>
											<v-btn color="primary" @click="saveEmailForward()" :loading="dialog_domain.forwards.loading">Save</v-btn>
										</v-card-actions>
									</v-card>
								</v-dialog>
							</v-window-item>
							<v-window-item value="mailgun" v-if="dialog_domain.details.mailgun_id" :transition="false" :reverse-transition="false">
							<v-card flat>
								<v-card-text>

									<v-container v-if="mailgun.loading" class="text-center pa-5">
										<v-progress-circular
											indeterminate
											color="primary"
											size="32"
										></v-progress-circular>
										<p class="mt-3 mb-0">Loading Mailgun details...</p>
									</v-container>

									<v-container v-else-if="mailgun.data.domain">

										<div v-if="!mailgun.data.domain.state || mailgun.data.domain.state === 'unverified'">
                                            <h4>Verify Domain</h4>
                                            <p>Please add the following DNS records to verify your domain:</p>
                                            
                                            <v-card variant="tonal" class="my-4 pa-2" v-if="mailgun.data.sending_dns_records && mailgun.data.sending_dns_records.length > 0">
                                                <div class="text-subtitle-1">Sending Records</div>
                                                <v-list density="compact" class="py-0" bg-color="transparent">
                                                    <template v-for="(record, index) in mailgun.data.sending_dns_records" :key="'send-'+index">
                                                        <div class="px-2 pt-2">
                                                            <v-list-item-subtitle class="py-1 align-center">
                                                                <v-icon 
                                                                    :icon="record.valid == 'valid' ? 'mdi-check-circle' : 'mdi-close-circle'"
                                                                    :color="record.valid == 'valid' ? 'success' : 'error'"
                                                                    size="small"
                                                                    class="mr-2"
                                                                ></v-icon>
                                                                {{ record.record_type }} record
                                                            </v-list-item-subtitle>
                                                            
                                                            <v-list-item
                                                                @click="copyText(record.name)"
                                                                title="Name"
                                                                :subtitle="record.name"
                                                                append-icon="mdi-content-copy"
                                                                class="copyable-list-item ml-3"
                                                            >
                                                                <template v-slot:subtitle="{ subtitle }">
                                                                    <code style="word-break: break-all; white-space: normal;">{{ subtitle }}</code>
                                                                </template>
                                                            </v-list-item>
                                                            
                                                            <v-list-item
                                                                @click="copyText(record.value)"
                                                                title="Value"
                                                                :subtitle="record.value"
                                                                append-icon="mdi-content-copy"
                                                                class="copyable-list-item ml-3"
                                                            >
                                                                <template v-slot:subtitle="{ subtitle }">
                                                                    <code style="word-break: break-all; white-space: normal;">{{ subtitle }}</code>
                                                                </template>
                                                            </v-list-item>
                                                        </div>
                                                        <v-divider v-if="index < mailgun.data.sending_dns_records.length - 1" class="mt-2"></v-divider>
                                                    </template>
                                                </v-list>
                                            </v-card>
                                            
                                            <v-card variant="tonal" class="my-4 pa-2" v-if="mailgun.data.receiving_dns_records && mailgun.data.receiving_dns_records.length > 0">
                                                <div class="text-subtitle-1">Receiving Records (MX)</div>
                                                <v-list density="compact" class="py-0" bg-color="transparent">
                                                    <template v-for="(record, index) in mailgun.data.receiving_dns_records" :key="'rec-'+index">
                                                        <div class="px-2 pt-2">
                                                            <v-list-item-subtitle class="py-1 align-center">
                                                                <v-icon 
                                                                    :icon="record.valid == 'valid' ? 'mdi-check-circle' : 'mdi-close-circle'"
                                                                    :color="record.valid == 'valid' ? 'success' : 'error'"
                                                                    size="small"
                                                                    class="mr-2"
                                                                ></v-icon>
                                                                {{ record.record_type }} record
                                                            </v-list-item-subtitle>

															<v-list-item
                                                                @click="copyText(record.name)"
                                                                title="Name"
                                                                :subtitle="record.name"
                                                                append-icon="mdi-content-copy"
                                                                class="copyable-list-item ml-3"
                                                            >
                                                                <template v-slot:subtitle="{ subtitle }">
                                                                    <code style="word-break: break-all; white-space: normal;">{{ subtitle }}</code>
                                                                </template>
                                                            </v-list-item>
                                                            
                                                            <v-list-item
                                                                @click="copyText(record.priority)"
                                                                title="Priority"
                                                                :subtitle="record.priority"
                                                                append-icon="mdi-content-copy"
                                                                class="copyable-list-item ml-3"
                                                            >
                                                                <template v-slot:subtitle="{ subtitle }">
                                                                    <code>{{ subtitle }}</code>
                                                                </template>
                                                            </v-list-item>
                                                            
                                                            <v-list-item
                                                                @click="copyText(record.value)"
                                                                title="Value"
                                                                :subtitle="record.value"
                                                                append-icon="mdi-content-copy"
                                                                class="copyable-list-item ml-3"
                                                            >
                                                                <template v-slot:subtitle="{ subtitle }">
                                                                    <code style="word-break: break-all; white-space: normal;">{{ subtitle }}</code>
                                                                </template>
                                                            </v-list-item>
                                                        </div>
                                                        <v-divider v-if="index < mailgun.data.receiving_dns_records.length - 1" class="mt-2"></v-divider>
                                                    </template>
                                                </v-list>
                                            </v-card>

                                            <v-btn color="primary" @click="verifyMailgunDomain(dialog_domain)" :loading="mailgun.loadingVerify" prepend-icon="mdi-check-network">
                                                Attempt to Verify Domain
                                            </v-btn>

											<v-btn 
                                                color="secondary" 
                                                @click="copyMailgunRecordsToClipboard()" 
                                                prepend-icon="mdi-content-copy"
                                                variant="tonal"
                                                class="ml-2"
                                            >
                                                Copy Records
                                            </v-btn>
                                        </div>

										<div v-else>
											<v-list density="compact">
												<v-list-item>
													<v-list-item-subtitle>Status</v-list-item-subtitle>
													<v-list-item-title class="text-capitalize">
														<v-chip :color="mailgun.data.domain.state == 'active' ? 'success' : 'warning'" size="small" label variant="flat">
															{{ mailgun.data.domain.state }}
														</v-chip>
													</v-list-item-title>
												</v-list-item>
												<v-list-item>
													<v-list-item-subtitle>Zone</v-list-item-subtitle>
													<v-list-item-title>{{ mailgun.data.domain.name }}</v-list-item-title>
												</v-list-item>
												<v-list-item>
													<v-list-item-subtitle>Created At</v-list-item-subtitle>
													<v-list-item-title>{{ new Date(mailgun.data.domain.created_at).toLocaleString() }}</v-list-item-title>
												</v-list-item>
											</v-list>
											
											<v-btn color="primary" class="mt-2 mr-2" @click="viewDomainMailgunLogs(dialog_domain.domain)">View Logs</v-btn>
											
											<v-btn
												color="primary"
												@click="openMailgunDeployDialog()"
												:disabled="!dialog_domain.connected_sites || dialog_domain.connected_sites.length === 0 || mailgun.loadingDeploy"
												:loading="mailgun.loadingDeploy"
												class="mt-2"
											>
												Deploy to...
												<v-icon end>mdi-chevron-down</v-icon>
											</v-btn>
											
											<p v-if="!dialog_domain.connected_sites || dialog_domain.connected_sites.length === 0" class="mt-4 pa-4 text-medium-emphasis text-caption">
												No connected sites found for this domain.
											</p>
										</div>

									</v-container>
									
									<v-container v-else class="text-center pa-5">
										<p>This domain has not been configured with Mailgun.</p>
										<v-btn color="primary" @click="mailgun.subdomainDialog = true">
											Setup Mailgun
										</v-btn>
									</v-container>

								</v-card-text>
							</v-card>
						</v-window-item>
						</v-window>
						</v-container>
					</v-card>
				</v-sheet>
			</v-card>
			<v-card v-if="route == 'health'" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Listing {{ filterSitesWithErrors.length }} sites with issues</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items></v-toolbar-items>
				</v-toolbar>
				<v-card-text>
					<v-alert type="info" variant="text">
						Results from daily scans of home pages. Web console errors are extracted from Google Chrome via Lighthouse CLI. Helpful for tracking down wide range of issues.
					</v-alert>
					<v-card v-for="site in filterSitesWithErrors" flat class="mb-2" :key="site.site_id">
					<v-toolbar flat class="px-4">
						<v-img :src="`${remote_upload_uri}${site.site}_${site.site_id}/production/screenshots/${site.screenshot_base}_thumb-100.jpg`" class="elevation-1 mr-3" max-width="50" v-show="site.screenshot_base"></v-img>
						<v-toolbar-title>{{ site.name }}</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-toolbar-items>
						<v-btn size="small" variant="text" @click="scanErrors( site )">Scan <v-icon class="ml-1">mdi-sync</v-icon></v-btn>
						<v-btn size="small" variant="text" :href="`http://${site.name}`" target="_blank">View <v-icon class="ml-1">mdi-open-in-new</v-icon></v-btn>
						<v-btn size="small" variant="text" @click="copySSH( site )">SSH <v-icon class="ml-1">mdi-content-copy</v-icon></v-btn>
						<v-btn size="small" variant="text" @click="showLogEntry( site.site_id )" v-show="role == 'administrator'">Log <v-icon class="ml-1">mdi-check</v-icon></v-btn>
						<v-chip class="mt-4 ml-2" label :value="true">{{ site.console_errors.length }} issues</v-chip>
						</v-toolbar-items>
					</v-toolbar>
					<v-card class="elevation-0 mx-auto" v-for="error in site.console_errors" :key="error.source">
						<v-card-item>
						<v-card-title>{{ error.source }}</v-card-title>
						<v-card-subtitle><a :href="error.url">{{ error.url }}</a></v-card-subtitle>
						</v-card-item>
						<v-card-text>
						<pre><code>{{ error.description }}</code></pre>
						</v-card-text>
					</v-card>
					<v-overlay absolute :model-value="site.loading">
						<v-progress-circular indeterminate size="64" width="4"></v-progress-circular>
					</v-overlay>
					</v-card>
				</v-card-text>
			</v-card>
			<v-card v-if="route == 'vulnerability-scans'" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Listing vulnerabilities</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text>
				<v-alert type="info" variant="text">
						Results from daily scans of using Wordfence.
				</v-alert>
					<v-card v-for="plugin in vulnerabilities.plugin" class="mb-2">
					<v-toolbar light flat>
						<v-toolbar-title>{{ plugin.title }}</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-toolbar-items>
							<v-btn size="small" density="compact" text @click="scanErrors( plugin.environments.map( env => env.enviroment_id ) )">
								Sync <v-icon class="ml-1">mdi-sync</v-icon>
							</v-btn>
							<v-dialog :transition="false" max-width="800">
								<template v-slot:activator="{ props }">
								<v-btn v-bind="props" size="small" density="compact" text>
									Console <v-icon class="ml-1">mdi-console</v-icon> 
								</v-btn>
								</template>
								<template v-slot:default="{ isActive }">
								<v-card>
									<v-toolbar flat dark color="primary" class="mb-3">
										<v-btn icon dark @click="isActive.value = false">
											<v-icon>mdi-close</v-icon>
										</v-btn>
										<v-toolbar-title>Console</v-toolbar-title>
										<v-spacer></v-spacer>
									</v-toolbar>
									<v-card-text>
									<v-chip size="small" label v-for="enviroment in plugin.environments" class="mr-1 mb-1">{{ enviroment.home_url }}</v-chip>
									<v-textarea
										auto-grow
										outlined
										rows="4"
										dense
										hint="Custom bash script or WP-CLI commands"
										persistent-hint
										:model-value="script.code"
										@update:model-value="script.code = $event"
										spellcheck="false"
										class="code mt-1"
									>
									</v-textarea>
									<v-btn size="small" color="primary" dark @click="runCustomCodeBulkEnvironments( plugin.environments )">Run Custom Code</v-btn>
									</v-card-text>
								</v-card>
								</template>
							</v-dialog>
							<v-btn size="small" density="compact" text @click="showLogEntry( plugin.environments.map( env => env.enviroment_id ) )" v-show="role == 'administrator'">
								Log <v-icon class="ml-1">mdi-check</v-icon>
							</v-btn>
							<v-chip class="mt-4 ml-2" label :input-value="true">{{ plugin.environments.length }} affected environments</v-chip>
						</v-toolbar-items>
					</v-toolbar>
					<v-table density="compact" class="disable_hover">
						<template v-slot:default>
						<tbody>
							<tr v-for="enviroment in plugin.environments">
							<td style="width:100px">{{ enviroment.home_url }}</td>
							<td><v-chip label small>{{ enviroment.environment }}</v-chip> 
							<v-menu open-on-hover offset-y v-for="vulnerability in enviroment.vulnerabilities">
							<template v-slot:activator="{ props }">
								<v-chip v-bind="props" class="mr-1" link label size="small" :href="vulnerability.link" target="_blank" :color="cvssClass(vulnerability.cvss_rating)">
								{{ vulnerability.cvss_score || "-" }}
								</v-chip>
							</template>
							<v-card max-width="500px">
								<v-card-title>{{ vulnerability.cve }}</v-card-title>
								<v-card-subtitle>{{ vulnerability.title }}</v-card-subtitle>
								<v-card-text>
									<strong>{{ vulnerability.remediation }}</strong>
								</v-card-text>
							</v-card>
							</v-menu>
							</tr>
						</tbody>
						</template>
					</v-table>
					</v-card>
				</v-card-text>
			</v-card>
			<v-card v-if="route == 'cookbook'" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Listing {{ filteredRecipes.length }} recipes</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-btn variant="text" @click="new_recipe.show = true">Add recipe <v-icon dark>mdi-plus</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text>
				<v-alert type="info" variant="tonal">
					Warning, this is for developers only 💻. The cookbook contains user made "recipes" or scripts which are deployable to one or many sites. Bash script and WP-CLI commands welcomed. For ideas refer to <code><a href="https://captaincore.io/cookbook/" target="_blank">captaincore.io/cookbook</a></code>.
				</v-alert>
				</v-card-text>
				<v-data-table
				no-data-text="No recipes found."
					:headers="[{ title: 'Title', value: 'title' }]"
					:items="filteredRecipes"
					:sort-by="[{ key: 'title', order: 'asc' }]"
					:items-per-page="100"
					:items-per-page-options="[100,250,500,{'title':'All','value':-1}]"
					item-value="recipe_id"
					hover
					class="clickable-rows"
					@click:row="(event, { item }) => editRecipe( item.recipe_id )"
				>
				</v-data-table>
			</v-card>
			<v-card v-if="route == 'handbook' && role == 'administrator'" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Listing {{ processes.length }} processes</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-tooltip location="top">
							<template v-slot:activator="{ props }">
								<v-btn variant="text" @click="fetchProcessLogs()" v-bind="props" icon="mdi-timeline-text-outline"></v-btn>
							</template>
							<span>Log History</span>
						</v-tooltip>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-tooltip location="top">
							<template v-slot:activator="{ props }">
								<v-btn variant="text" @click="showLogEntryGeneric()" v-bind="props" icon="mdi-check-bold"></v-btn>
							</template>
							<span>Add Log Entry</span>
						</v-tooltip>
						<v-divider vertical class="mx-1" inset></v-divider>
						<v-btn variant="text" @click="new_process.show = true">Add process <v-icon dark>mdi-plus</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text style="max-height: 100%;">
				<v-container fluid>
					<v-row>
					<v-col cols="12" v-for="process in processes">
						<v-card :hover="true" @click="viewProcess( process.process_id )">
						<v-card-title class="pt-2">
							<div>
							<span class="text-h6">{{ process.name }}</span>
							<v-chip color="primary" text-color="white" v-show="process.roles != ''">{{ process.roles }}</v-chip>
							<div class="text-caption">
								<v-icon v-show="process.time_estimate != ''" style="padding:0px 5px">mdi-clock-outline</v-icon>{{ process.time_estimate }}
								<v-icon v-show="process.repeat_interval != '' && process.repeat_interval != null" style="padding:0px 5px">mdi-calendar-repeat</v-icon>{{ process.repeat_interval }}
								<v-icon v-show="process.repeat_quantity != '' && process.repeat_quantity != null" style="padding:0px 5px">mdi-repeat</v-icon>{{ process.repeat_quantity }}
							</div>
							</div>
						</v-card-title>
						</v-card>
					</v-col>
					</v-row>
				</v-container>
				</v-card-text>
			</v-card>
			<v-card v-if="route == 'configurations' && ( role == 'administrator' || role == 'owner' )" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Configurations</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-tabs bg-color="primary" v-model="configurations_step">
					<v-tab value="branding">Branding</v-tab>
					<v-tab value="tasks" v-show="role == 'administrator'">Scheduled Tasks</v-tab>
					<v-tab value="providers">Providers</v-tab>
					<v-tab value="billing" v-show="role == 'administrator'">Billing</v-tab>
				</v-tabs>
				<v-window v-model="configurations_step">
					<v-window-item value="branding" :transition="false" :reverse-transition="false">
						<v-card flat>
							<v-card-text>
								<v-row>
									<v-col cols="12" md="4">
										<v-text-field v-model="configurations.name" label="Name" variant="underlined"></v-text-field>
										<v-switch v-model="configurations.logo_only" label="Show only logo" color="primary" inset hide-details></v-switch>
									</v-col>
									<v-col cols="12" md="8">
										<v-text-field v-model="configurations.url" label="URL" variant="underlined"></v-text-field>
									</v-col>
								</v-row>
								<v-row>
									<v-col cols="12" md="8">
										<v-text-field v-model="configurations.logo" label="Logo URL" variant="underlined"></v-text-field>
									</v-col>
									<v-col cols="12" md="4">
										<v-text-field v-model="configurations.logo_width" label="Logo Width" variant="underlined"></v-text-field>
									</v-col>
								</v-row>
								<span class="text-body-2">DNS Labels</span>
								<v-row>
									<v-col cols="12" md="9">
										<v-textarea v-model="configurations.dns_introduction" label="Introduction" auto-grow rows="3" variant="underlined"></v-textarea>
									</v-col>
									<v-col cols="12" md="3">
										<v-textarea v-model="configurations.dns_nameservers" label="Nameservers" spellcheck="false" auto-grow variant="underlined"></v-textarea>
									</v-col>
								</v-row>
								<span class="text-body-2">Theme colors</span>
								<v-row>
									<v-col class="shrink">
										<v-text-field persistent-hint hint="Primary" v-model="currentThemeColors.primary" class="ma-0 pa-0" variant="solo">
											<template v-slot:append-inner>
												<v-menu v-model="colors.primary" location="bottom" :close-on-content-click="false">
													<template v-slot:activator="{ props }">
														<div :style="{ backgroundColor: currentThemeColors.primary, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
													</template>
													<v-card>
														<v-card-text class="pa-0">
															<v-color-picker v-model="currentThemeColors.primary"></v-color-picker>
														</v-card-text>
													</v-card>
												</v-menu>
											</template>
										</v-text-field>
									</v-col>
									<v-col class="shrink">
										<v-text-field persistent-hint hint="Secondary" v-model="currentThemeColors.secondary" class="ma-0 pa-0" variant="solo">
											<template v-slot:append-inner>
												<v-menu v-model="colors.secondary" location="bottom" :close-on-content-click="false">
													<template v-slot:activator="{ props }">
														<div :style="{ backgroundColor: currentThemeColors.secondary, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
													</template>
													<v-card>
														<v-card-text class="pa-0">
															<v-color-picker v-model="currentThemeColors.secondary"></v-color-picker>
														</v-card-text>
													</v-card>
												</v-menu>
											</template>
										</v-text-field>
									</v-col>
									<v-col class="shrink">
										<v-text-field persistent-hint hint="Accent" v-model="currentThemeColors.accent" class="ma-0 pa-0" variant="solo">
											<template v-slot:append-inner>
												<v-menu v-model="colors.accent" location="bottom" :close-on-content-click="false">
													<template v-slot:activator="{ props }">
														<div :style="{ backgroundColor: 'rgb(var(--v-theme-accent))', cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
													</template>
													<v-card>
														<v-card-text class="pa-0">
															<v-color-picker v-model="currentThemeColors.accent"></v-color-picker>
														</v-card-text>
													</v-card>
												</v-menu>
											</template>
										</v-text-field>
									</v-col>
									<v-col class="shrink">
										<v-text-field persistent-hint hint="Error" v-model="currentThemeColors.error" class="ma-0 pa-0" variant="solo">
											<template v-slot:append-inner>
												<v-menu v-model="colors.error" location="bottom" :close-on-content-click="false">
													<template v-slot:activator="{ props }">
														<div :style="{ backgroundColor: currentThemeColors.error, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
													</template>
													<v-card>
														<v-card-text class="pa-0">
															<v-color-picker v-model="currentThemeColors.error"></v-color-picker>
														</v-card-text>
													</v-card>
												</v-menu>
											</template>
										</v-text-field>
									</v-col>
									<v-col class="shrink">
										<v-text-field persistent-hint hint="Info" v-model="currentThemeColors.info" class="ma-0 pa-0" variant="solo">
											<template v-slot:append-inner>
												<v-menu v-model="colors.info" location="bottom" :close-on-content-click="false">
													<template v-slot:activator="{ props }">
														<div :style="{ backgroundColor: currentThemeColors.info, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
													</template>
													<v-card>
														<v-card-text class="pa-0">
															<v-color-picker v-model="currentThemeColors.info"></v-color-picker>
														</v-card-text>
													</v-card>
												</v-menu>
											</template>
										</v-text-field>
									</v-col>
									<v-col class="shrink">
										<v-text-field persistent-hint hint="Success" v-model="currentThemeColors.success" class="ma-0 pa-0" variant="solo">
											<template v-slot:append-inner>
												<v-menu v-model="colors.success" location="bottom" :close-on-content-click="false">
													<template v-slot:activator="{ props }">
														<div :style="{ backgroundColor: currentThemeColors.success, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
													</template>
													<v-card>
														<v-card-text class="pa-0">
															<v-color-picker v-model="currentThemeColors.success"></v-color-picker>
														</v-card-text>
													</v-card>
												</v-menu>
											</template>
										</v-text-field>
									</v-col>
									<v-col class="shrink">
										<v-text-field persistent-hint hint="Warning" v-model="currentThemeColors.warning" class="ma-0 pa-0" variant="solo">
											<template v-slot:append-inner>
												<v-menu v-model="colors.warning" location="bottom" :close-on-content-click="false">
													<template v-slot:activator="{ props }">
														<div :style="{ backgroundColor: currentThemeColors.warning, cursor: 'pointer', height: '30px', width: '30px', borderRadius: '4px', transition: 'border-radius 200ms ease-in-out' }" v-bind="props"></div>
													</template>
													<v-card>
														<v-card-text class="pa-0">
															<v-color-picker v-model="currentThemeColors.warning"></v-color-picker>
														</v-card-text>
													</v-card>
												</v-menu>
											</template>
										</v-text-field>
									</v-col>
								</v-row>
								<v-row>
									<v-col><v-btn @click="resetColors">Reset colors</v-btn></v-col>
								</v-row>
							</v-card-text>
						</v-card>
					</v-window-item>
					<v-window-item value="tasks" :transition="false" :reverse-transition="false">
						<v-card flat>
							<v-card-text>
								<span class="text-body-2">Scheduled Tasks</span>
								<v-row>
									<v-col>
										<v-data-table
											:items="configurations.scheduled_tasks"
											:items-per-page="-1"
											class="elevation-0"
											hide-default-footer
										>
											<template v-slot:headers>
												<thead>
													<tr>
														<th width="20px"></th>
														<th width="150px">Target</th>
														<th width="150px">Command</th>
														<th>Arguments</th>
														<th width="150px">Repeat</th>
														<th width="150px">Repeat Quantity</th>
														<th width="225px">Next Run</th>
														<th></th>
													</tr>
												</thead>
											</template>
											<template v-slot:item="{ item, index }">
												<tr>
													<td><v-icon>mdi-repeat</v-icon></td>
													<td><v-select :items="['all', 'production', 'staging', 'custom']" v-model="item.target" hide-details variant="underlined" density="compact"></v-select></td>
													<td><v-select :items="['backup', 'monitor', 'quicksave', 'scan-errors', 'ssh', 'update']" v-model="item.command" hide-details variant="underlined" density="compact"></v-select></td>
													<td><v-text-field v-model="item.arguments" hide-details variant="underlined" density="compact"></v-text-field></td>
													<td><v-select :items="['Hourly', 'Daily', 'Weekly', 'Quarterly', 'Biannually', 'Yearly']" v-model="item.repeat_interval" hide-details variant="underlined" density="compact"></v-select></td>
													<td><v-text-field v-model="item.repeat_quantity" type="number" hint="Example: 2 or 3 times" persistent-hint variant="underlined" density="compact"></v-text-field></td>
													<td>
														<v-menu v-model="item.date_selector" :close-on-content-click="false" location="bottom">
															<template v-slot:activator="{ props }">
																<v-text-field
																	v-model="item.next_run"
																	label=""
																	prepend-icon="mdi-calendar"
																	readonly
																	v-bind="props"
																	variant="underlined"
																	density="compact"
																></v-text-field>
															</template>
															<v-date-picker @update:model-value="keepTimestampNextRun($event, item); item.date_selector = false"></v-date-picker>
														</v-menu>
													</td>
													<td><v-btn size="small" variant="text" icon="mdi-delete" @click="configurations.scheduled_tasks.splice( index, 1 )"></v-btn></td>
												</tr>
											</template>
											<template v-slot:bottom>
												<div class="pa-4">
													<v-btn @click="configurations.scheduled_tasks.push( { repeat_interval:'Daily', repeat_at:'9pm',command:'backup',target:'all' })">New scheduled task</v-btn>
												</div>
											</template>
										</v-data-table>
									</v-col>
								</v-row>
							</v-card-text>
						</v-card>
					</v-window-item>
					<v-window-item value="providers" :transition="false" :reverse-transition="false">
						<v-toolbar flat color="transparent">
							<v-toolbar-title>Listing {{ providers.length }} providers</v-toolbar-title>
							<v-spacer></v-spacer>
							<v-btn variant="text" @click="dialog_new_provider.show = true">Add Provider <v-icon>mdi-plus</v-icon></v-btn>
						</v-toolbar>
						<v-data-table
							:headers="[{ title: 'Name', key: 'name' },{ title: 'Created', key: 'created_at' }]"
							:items="providers"
							:items-per-page="100"
							:items-per-page-options="[100,250,500,{'title':'All','value':-1}]"
							@click:row="(event, { item }) => editProvider(item)"
							hover
							style="cursor:pointer"
						>
							<template v-slot:item.created_at="{ item }">
								{{ pretty_timestamp( item.created_at ) }}
							</template>
						</v-data-table>
					</v-window-item>
					<v-window-item value="billing" :transition="false" :reverse-transition="false">
						<v-card flat>
							<v-card-text>
								<span class="text-body-2">WooCommerce Products</span>
								<v-row class="mb-7">
									<v-col>
										<v-select v-model="configurations.woocommerce.hosting_plan" :items='<?php echo json_encode( CaptainCore\Configurations::products() ); ?>' item-value="id" item-title="name" label="Hosting Plan" hide-details variant="underlined"></v-select>
									</v-col>
									<v-col>
										<v-select v-model="configurations.woocommerce.addons" :items='<?php echo json_encode( CaptainCore\Configurations::products() ); ?>' item-value="id" item-title="name" label="Addons" hide-details variant="underlined"></v-select>
									</v-col>
									<v-col>
										<v-select v-model="configurations.woocommerce.charges" :items='<?php echo json_encode( CaptainCore\Configurations::products() ); ?>' item-value="id" item-title="name" label="Charges" hide-details variant="underlined"></v-select>
									</v-col>
									<v-col>
										<v-select v-model="configurations.woocommerce.credits" :items='<?php echo json_encode( CaptainCore\Configurations::products() ); ?>' item-value="id" item-title="name" label="Credits" hide-details variant="underlined"></v-select>
									</v-col>
									<v-col>
										<v-select v-model="configurations.woocommerce.usage" :items='<?php echo json_encode( CaptainCore\Configurations::products() ); ?>' item-value="id" item-title="name" label="Usage" hide-details variant="underlined"></v-select>
									</v-col>
								</v-row>
								<span class="text-body-2">Hosting Plans</span>
								<v-row v-for="(plan, index) in configurations.hosting_plans" :key="index" align="center">
									<v-col>
										<v-text-field v-model="plan.name" label="Name" variant="underlined" hide-details></v-text-field>
									</v-col>
									<v-col style="max-width:100px">
										<v-text-field v-model="plan.interval" label="Interval" hint="# of months" persistent-hint variant="underlined" hide-details></v-text-field>
									</v-col>
									<v-col style="max-width:100px">
										<v-text-field v-model="plan.price" label="Price" variant="underlined" hide-details></v-text-field>
									</v-col>
									<v-col style="max-width:150px">
										<v-text-field v-model="plan.limits.visits" label="Visits Limits" variant="underlined" hide-details></v-text-field>
									</v-col>
									<v-col style="max-width:150px">
										<v-text-field v-model="plan.limits.storage" label="Storage Limits" variant="underlined" hide-details></v-text-field>
									</v-col>
									<v-col style="max-width:120px">
										<v-text-field v-model="plan.limits.sites" label="Sites Limits" variant="underlined" hide-details></v-text-field>
									</v-col>
									<v-col class="ma-0 pa-0" style="max-width:46px">
										<v-btn color="red" icon="mdi-delete" @click="deletePlan( index )" variant="text"></v-btn>
									</v-col>
								</v-row>
								<v-row>
									<v-col><v-btn @click="addAdditionalPlan()" variant="outlined" color="secondary">Add Additional Plan</v-btn></v-col>
								</v-row>
								<v-divider class="my-5"></v-divider>
								<span class="text-body-2">Usage Pricing</span>
								<v-row>
									<v-col style="max-width:200px"><v-text-field label="Sites Quantity" v-model="configurations.usage_pricing.sites.quantity" variant="underlined"></v-text-field></v-col>
									<v-col style="max-width:150px"><v-text-field label="Sites Cost" v-model="configurations.usage_pricing.sites.cost" variant="underlined"></v-text-field></v-col>
									<v-col style="max-width:150px"><v-text-field label="Sites Interval" v-model="configurations.usage_pricing.sites.interval" hint="# of months" persistent-hint variant="underlined"></v-text-field></v-col>
								</v-row>
								<v-row>
									<v-col style="max-width:200px"><v-text-field label="Storage Quantity (GB)" v-model="configurations.usage_pricing.storage.quantity" variant="underlined"></v-text-field></v-col>
									<v-col style="max-width:150px"><v-text-field label="Storage Cost" v-model="configurations.usage_pricing.storage.cost" variant="underlined"></v-text-field></v-col>
									<v-col style="max-width:150px"><v-text-field label="Storage Interval" v-model="configurations.usage_pricing.storage.interval" hint="# of months" persistent-hint variant="underlined"></v-text-field></v-col>
								</v-row>
								<v-row>
									<v-col style="max-width:200px"><v-text-field label="Traffic Quantity (pageviews)" v-model="configurations.usage_pricing.traffic.quantity" variant="underlined"></v-text-field></v-col>
									<v-col style="max-width:150px"><v-text-field label="Traffic Cost" v-model="configurations.usage_pricing.traffic.cost" variant="underlined"></v-text-field></v-col>
									<v-col style="max-width:150px"><v-text-field label="Traffic Interval" v-model="configurations.usage_pricing.traffic.interval" hint="# of months" persistent-hint variant="underlined"></v-text-field></v-col>
								</v-row>
								<v-divider class="my-5"></v-divider>
								<span class="text-body-2">Maintenance Pricing</span>
								<v-row>
									<v-col style="max-width:150px"><v-text-field label="Cost Per Site" v-model="configurations.maintenance_pricing.cost" variant="underlined"></v-text-field></v-col>
									<v-col style="max-width:150px"><v-text-field label="Interval" v-model="configurations.maintenance_pricing.interval" hint="# of months" persistent-hint variant="underlined"></v-text-field></v-col>
								</v-row>
							</v-card-text>
						</v-card>
					</v-window-item>
				</v-window>
				<v-row>
					<v-col>
						<v-card flat>
							<v-card-text class="justify-end">
								<v-btn color="primary" @click="saveGlobalConfigurations()">Save Configurations</v-btn>
							</v-card-text>
						</v-card>
					</v-col>
				</v-row>
			</v-card>
			<v-card v-if="route == 'billing'" flat border="thin" rounded="xl">
				<v-toolbar flat v-show="dialog_billing.step == 1" color="transparent">
					<v-toolbar-title>Billing</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-container class="pt-0">
				<div v-show="dialog_billing.step == 1">
				<v-toolbar color="primary" density="compact" flat rounded="lg">
					<v-tabs v-model="billing_tabs" density="compact" align-tabs="start" hide-slider>
						<v-tab :key="1" value="tab-Billing-Invoices">
							Invoices <v-icon size="24" class="ml-1">mdi-receipt-text</v-icon>
						</v-tab>
						<v-tab :key="2" value="tab-Billing-Overview">
							My Plan <v-icon size="24" class="ml-1">mdi-chart-donut</v-icon>
						</v-tab>
						<v-tab :key="3" value="tab-Billing-Payment-Methods">
							Payment Methods <v-icon size="24" class="ml-1">mdi-credit-card-outline</v-icon>
						</v-tab>
						<v-tab :key="4" value="tab-Billing-Address">
							Billing Address <v-icon size="24" class="ml-1">mdi-map-marker</v-icon>
						</v-tab>
					</v-tabs>
					</v-toolbar>
					<v-window v-model="billing_tabs" style="background:transparent">
						<v-window-item value="tab-Billing-Invoices" :transition="false" :reverse-transition="false">
						<v-data-table
							:loading="billing_loading"
							:headers="[
								{ title: 'Order', key: 'order_id', width: '130px' },
								{ title: 'Date', key: 'date' },
								{ title: 'Status', key: 'status' },
								{ title: 'Total', key: 'total', width: '120px' },
								{ title: '', key: 'actions', width: '140px', sortable: false }]"
							:items="billing.invoices"
							style="background:transparent"
						>
						<template v-slot:item.order_id="{ item }">
							#{{ item.order_id }}
						</template>
						<template v-slot:item.total="{ item }">
							${{ item.total }}
						</template>
						<template v-slot:item.actions="{ item }">
							<v-btn size="small" @click="goToPath( `/billing/${item.order_id}`)" color="primary">Show Invoice</v-btn>
						</template>
						</v-data-table>
						</v-window-item>
						<v-window-item value="tab-Billing-Overview" :transition="false" :reverse-transition="false">
						<v-data-table
							:loading="billing_loading"
							:headers="[
								{ title: 'Account', key: 'account_id', width: '100px' },
								{ title: 'Name', key: 'name' },
								{ title: 'Renewal Date', key: 'next_renewal' },
								{ title: 'Plan', key: 'plan' },
								{ title: 'Price', key: 'price' },
								{ title: 'Status', key: 'status' },
								{ title: '', key: 'actions', width: '140px', sortable: false }]"
							:items="billing.subscriptions"
							style="background:transparent"
						>
						<template v-slot:item.account_id="{ item }">
							#{{ item.account_id }}
						</template>
						<template v-slot:item.plan="{ item }">
							{{ item.plan.name }}
						</template>
						<template v-slot:item.price="{ item }">
							<span v-html="my_plan_usage_estimate( item.plan )"></span>
						</template>
						<template v-slot:item.next_renewal="{ item }">
							<span v-if="item.plan.next_renewal != '' && item.plan.next_renewal != null">{{ pretty_timestamp( item.plan.next_renewal ) }}</span>
						</template>
						<template v-slot:item.actions="{ item }">
							<v-btn color="primary" size="small" @click="customerModifyPlan( item )">Modify Plan</v-btn>
						</template>
						</v-data-table>
						</v-window-item>
						<v-window-item value="tab-Billing-Payment-Methods" :transition="false" :reverse-transition="false">
						<v-data-table
							v-if="billing.payment_methods"
							:loading="billing_loading"
							:headers="[
								{ title: 'Method', key: 'method.brand' },
								{ title: 'Expires', key: 'expires' },
								{ title: '', key: 'actions', width: '204px', align: 'end', sortable: false }
							]"
							:items="billing.payment_methods"
							hide-default-footer
							style="background:transparent"
						>
							<template v-slot:item.method.brand="{ item }">
								{{ item.method.brand }} ending in {{ item.method.last4 }}
							</template>
							<template v-slot:item.actions="{ item }">
								<v-btn size="small" disabled v-show="item.is_default">Primary Method</v-btn>
								<v-btn size="small" v-show="!item.is_default" @click="setAsPrimary( item.token )" color="primary">Set as Primary</v-btn>
								<v-btn color="red" icon="mdi-delete" size="small" @click="deletePaymentMethod( item.token )" variant="text"></v-btn>
							</template>
							<template v-slot:bottom>
								<div class="text-left pa-2">
									<v-dialog max-width="500" v-model="new_payment.show">
										<template v-slot:activator="{ props }">
											<v-btn size="small" v-bind="props" variant="tonal" class="ml-2 mb-2">
												Add new payment method
											</v-btn>
										</template>
										<v-card>
											<v-toolbar flat>
												<v-toolbar-title>New payment method</v-toolbar-title>
												<v-spacer></v-spacer>
												<v-btn @click="new_payment.show = false" icon="mdi-close" variant="text"></v-btn>
											</v-toolbar>
											<v-card-text class="mt-5">
												<div id="new-card-element"></div>
												<v-alert variant="text" density="compact" border="start" type="warning" v-show="new_payment.error != ''">
													{{ new_payment.error }}
												</v-alert>
											</v-card-text>
											<v-divider></v-divider>
											<v-card-actions>
												<v-spacer></v-spacer>
												<v-btn @click="addPaymentMethod" color="primary" variant="tonal">Add Payment Method</v-btn>
											</v-card-actions>
										</v-card>
									</v-dialog>
								</div>
							</template>
						</v-data-table>
					</v-window-item>
					<v-window-item value="tab-Billing-Address" :transition="false" :reverse-transition="false" class="pt-3">
						<template v-if="typeof billing.address == 'object'">
						<v-row no-gutters class="mx-3">
							<v-col class="ma-1"><v-text-field label="First Name" v-model="billing.address.first_name" variant="underlined"></v-text-field></v-col>
							<v-col class="ma-1"><v-text-field label="Last Name" v-model="billing.address.last_name" variant="underlined"></v-text-field></v-col>
							<v-col class="ma-1"><v-text-field label="Company name (optional)" v-model="billing.address.company" variant="underlined"></v-text-field></v-col>
						</v-row>
						<v-row no-gutters class="mx-3">
							<v-col class="ma-1"><v-text-field label="Street Address" persistent-hint hint="House number and street name" v-model="billing.address.address_1" variant="underlined"></v-text-field></v-col>
						</v-row>
						<v-row no-gutters class="mx-3">
							<v-col class="ma-1"><v-text-field label="" persistent-hint hint="Apartment, suite, unit, etc. (optional)" v-model="billing.address.address_2" variant="underlined"></v-text-field></v-col>
						</v-row>
						<v-row no-gutters class="mx-3">
							<v-col class="ma-1"><v-text-field label="Town" v-model="billing.address.city" variant="underlined"></v-text-field></v-col>
							<v-col class="ma-1">
								<v-autocomplete label="State" v-model="billing.address.state" :items="states_selected" v-if="states_selected.length > 0" variant="underlined"></v-autocomplete>
								<v-text-field label="State" v-model="billing.address.state" variant="underlined" v-else></v-text-field>
							</v-col>
							<v-col class="ma-1"><v-text-field label="Zip" v-model="billing.address.postcode" variant="underlined"></v-text-field></v-col>
							<v-col class="ma-1"><v-autocomplete label="Country" v-model="billing.address.country" :items="countries" @update:model-value="populateStates()" variant="underlined"></v-autocomplete></v-col>
						</v-row>
						<v-row no-gutters class="mx-3">
							<v-col class="ma-1"><v-text-field label="Phone" v-model="billing.address.phone" variant="underlined"></v-text-field></v-col>
							<v-col class="ma-1"><v-text-field label="Email" v-model="billing.address.email" variant="underlined"></v-text-field></v-col>
						</v-row>
						<v-row no-gutters class="mx-3 mb-3 text-left">
						<v-btn size="small" @click="updateBilling()" variant="tonal">
							Update Billing Address
						</v-btn>
						</v-row>
						</template>
						</v-window-item>
					</v-window>
				</div>
				<div v-show="dialog_billing.step == 2">
					<v-toolbar flat color="transparent">
						<v-toolbar-title v-show="dialog_invoice.loading == true">Loading...</v-toolbar-title>
						<v-toolbar-title v-show="dialog_invoice.loading == false">Invoice #{{ dialog_invoice.response.order_id }}</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-toolbar-items v-show="dialog_invoice.loading == false">
							<v-tooltip location="bottom">
								<template v-slot:activator="{ props }">
									<v-btn variant="text" @click="downloadPDF()" v-bind="props" icon="mdi-file-download"></v-btn>
									<a ref="download_pdf" href="#" style="display: none;"></a>
								</template>
								<span>Download PDF Invoice</span>
							</v-tooltip>
							<v-btn variant="text" @click="goToPath( '/billing' )"><v-icon>mdi-arrow-left</v-icon> Back</v-btn>
						</v-toolbar-items>
					</v-toolbar>
					<v-card-text v-show="dialog_invoice.loading == false">
					<v-card flat>
					<v-row>
						<v-overlay contained v-model="dialog_invoice.paying" class="align-center justify-center">
							<v-progress-circular indeterminate size="64"></v-progress-circular>
						</v-overlay>
						<v-col style="max-width:360px" v-show="dialog_invoice.response.status == 'pending' || dialog_invoice.response.status == 'failed'">
						<v-card class="mb-7" variant="outlined" v-if="typeof billing.address == 'object' && dialog_invoice.customer">
							<v-list-item class="pa-4">
								<div class="text-overline mb-4">
								Billing Details
								</div>
								<v-list-item-title class="text-h5 mb-1">
								{{ billing.address.first_name }} {{ billing.address.last_name }}
								</v-list-item-title>
								<v-list-item-subtitle>{{ billing.address.company }}</v-list-item-subtitle>
								<div v-html="billingAddress" class="text-body-2"></div>
								<div v-show="billing.address.phone != ''" class="text-body-2"><v-icon size="small">mdi-phone</v-icon> <a :href="'tel:'+ billing.address.phone">{{ billing.address.phone }}</a></div>
								<div v-show="billing.address.email != ''" class="text-body-2"><v-icon size="small">mdi-email</v-icon> <a :href="'mailto:'+ billing.address.email">{{ billing.address.email }}</a></div>
							</v-list-item>

							<v-card-actions>
							<v-btn color="primary" variant="outlined" @click="dialog_invoice.customer = false">
								Modify Billing Details
							</v-btn>
							</v-card-actions>
						</v-card>
						<v-card class="mb-7" max-width="360" flat border="thin" rounded="xl" v-else-if="typeof billing.address == 'object'">
							<v-form ref="billing_form" v-model="billing.valid" lazy-validation>
								<v-list-item class="pa-4">
										<div class="text-overline mb-4">
										Billing Details
										</div>
										<v-row no-gutters>
											<v-col class="pr-1"><v-text-field density="compact" label="First Name" v-model="billing.address.first_name" :rules="billing.rules.firstname" variant="underlined"></v-text-field></v-col>
											<v-col class="pl-1"><v-text-field density="compact" label="Last Name" v-model="billing.address.last_name" :rules="billing.rules.lastname" variant="underlined"></v-text-field></v-col>
										</v-row>
										<v-text-field density="compact" label="Company name (optional)" v-model="billing.address.company" variant="underlined"></v-text-field>
										<v-text-field density="compact" label="Street Address" persistent-hint hint="House number and street name" v-model="billing.address.address_1" :rules="billing.rules.address_1" variant="underlined"></v-text-field>
										<v-text-field density="compact" label="" persistent-hint hint="Apartment, suite, unit, etc. (optional)" v-model="billing.address.address_2" variant="underlined"></v-text-field>
										<v-text-field label="Town" v-model="billing.address.city" :rules="billing.rules.city" variant="underlined"></v-text-field>
										<v-autocomplete label="State" v-model="billing.address.state" :items="states_selected" v-if="states_selected.length > 0" variant="underlined"></v-autocomplete>
										<v-text-field label="State" v-model="billing.address.state" v-else variant="underlined"></v-text-field>
										<v-text-field density="compact" label="Zip" v-model="billing.address.postcode" :rules="billing.rules.zip" variant="underlined"></v-text-field>
										<v-autocomplete density="compact" label="Country" v-model="billing.address.country" :items="countries" @update:model-value="populateStates()" :rules="billing.rules.country" variant="underlined"></v-autocomplete>
										<v-text-field density="compact" label="Phone" v-model="billing.address.phone" variant="underlined"></v-text-field>
										<v-text-field density="compact" label="Email" v-model="billing.address.email" :rules="billing.rules.email" variant="underlined"></v-text-field>
								</v-list-item>
							</v-form>
						</v-card>
					</v-col>
					<v-col>
					<p class="mt-5">Order was created on <strong>{{ pretty_timestamp_epoch( dialog_invoice.response.created_at ) }}</strong> and is currently <strong>{{ dialog_invoice.response.status }} payment</strong>.</p>
						<v-data-table
							:headers="[
								{ title: 'Name', key: 'name', width: '120px', align: 'start' },
								{ title: 'Description', key: 'description', sortable: false },
								{ title: 'Quantity', key: 'quantity', width: '100px' },
								{ title: 'Total', key: 'total' } ]"
							:items='dialog_invoice.response.line_items'
							:items-per-page="-1"
							hide-default-footer
							class="mb-5 invoice"
							>
							<template v-slot:item.description="{ item }">
								<div v-for="(meta, i) in item.description" :key="i">
									<div v-if="item.name == 'Hosting Plan' && meta.value.split( '\n' ).length > 1">
									<v-card flat>
										<v-card-subtitle class="px-0 pb-0"><strong>{{ meta.value.split( "\n" )[0] }}</strong></v-card-subtitle>
										<v-card-text class="px-0">
										<div class="text-primary">
											{{ meta.value.split( "\n" )[2] }}
										</div>
										</v-card-text>
									</v-card>
									</div>
									<p v-else-if="meta.key == 'Details'">{{ meta.value }}</p>
								</div>
								<div v-if="typeof item.description == 'string'" v-html="item.description"></div>
							</template>
							<template v-slot:item.total="{ item }">
								<div v-html="item.total"></div>
							</template>
							<template v-slot:tfoot>
								<tr>
									<td colspan="3" class="text-right font-weight-bold">Total:</td>
									<td class="font-weight-bold text-subtitle-1">${{ dialog_invoice.response.total }}</td>
								</tr>
							</template>
						</v-data-table>
						<v-card class="mb-7" flat border="thin" rounded="xl" v-if="dialog_invoice.response.paid_on && dialog_invoice.response.status == 'completed'">
							<v-list-item class="pa-4">
								<div class="text-overline mb-4">
								Payment Details
								</div>
								<v-list-item-title class="mb-1">
								{{ dialog_invoice.response.payment_method }}
								</v-list-item-title>
								<v-list-item-subtitle>{{ dialog_invoice.response.paid_on }}</v-list-item-subtitle>
							</v-list-item>
						</v-card>
						<v-card class="mb-7" v-show="dialog_invoice.response.status == 'pending' || dialog_invoice.response.status == 'failed'" flat border="thin" rounded="xl">
						<v-list-item class="pa-4">
							<div class="text-overline mb-4">
							Credit Card
							</div>
							<v-container class="py-0 px-3">
								<v-radio-group v-model="dialog_invoice.payment_method" v-if="typeof billing.payment_methods != 'undefined'">
									<v-radio
										v-for="card in billing.payment_methods"
										:key="card.token"
										:label="`${card.method.brand} ending in ${card.method.last4} expires ${card.expires}`"
										:value="card.token"
									></v-radio>
									<v-radio label="Add new payment method" value="new"></v-radio>
								</v-radio-group>
							</v-container>
							<v-card max-width="450px" variant="outlined" v-show="dialog_invoice.payment_method == 'new'" class="mb-4">
								<v-card-text>
								<div id="card-element"></div>
								<v-alert variant="text" density="compact" border="start" type="error" v-show="dialog_invoice.error != ''" class="mt-4">
									{{ dialog_invoice.error }}
								</v-alert>
								</v-card-text>
							</v-card>
							<v-card class="d-flex flex-nowrap py-3" flat>
								<v-card flat class="mr-1"><v-img contain width="42px" src="/wp-content/plugins/woocommerce-gateway-stripe/assets/images/visa.svg" class="stripe-visa-icon stripe-icon" alt="Visa"></v-img></v-card>
								<v-card flat class="mr-1"><v-img contain width="42px" src="/wp-content/plugins/woocommerce-gateway-stripe/assets/images/amex.svg" class="stripe-amex-icon stripe-icon" alt="American Express"></v-img></v-card>
								<v-card flat class="mr-1"><v-img contain width="42px" src="/wp-content/plugins/woocommerce-gateway-stripe/assets/images/mastercard.svg" class="stripe-mastercard-icon stripe-icon" alt="Mastercard"></v-img></v-card>
								<v-card flat class="mr-1"><v-img contain width="42px" src="/wp-content/plugins/woocommerce-gateway-stripe/assets/images/discover.svg" class="stripe-discover-icon stripe-icon" alt="Discover"></v-img></v-card>
								<v-card flat class="mr-1"><v-img contain width="42px" src="/wp-content/plugins/woocommerce-gateway-stripe/assets/images/jcb.svg" class="stripe-jcb-icon stripe-icon" alt="JCB"></v-img></v-card>
								<v-card flat class="mr-1"><v-img contain width="42px" src="/wp-content/plugins/woocommerce-gateway-stripe/assets/images/diners.svg" class="stripe-diners-icon stripe-icon" alt="Diners"></v-img></v-card>
							</v-card>
							</v-list-item>
							</v-card>
							<v-btn color="primary" size="x-large" @click="verifyAndPayInvoice(dialog_invoice.response.order_id)" class="mb-7" v-show="dialog_invoice.response.status == 'pending' || dialog_invoice.response.status == 'failed'">Pay Invoice</v-btn>
						</v-col>
						</v-row>
						</v-card>
					</v-card-text>
				</div>
				</v-container>
			</v-card>
			<v-card v-if="route == 'defaults' && role == 'administrator'" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Site Defaults</v-toolbar-title>
					<v-spacer></v-spacer>
				</v-toolbar>
				<v-card-text>
				<v-alert variant="tonal" type="info" class="mb-4 mt-4">Configure default settings will can be applied by running the <strong>Deploy Defaults</strong> script.</v-alert>
				<v-row wrap>
					<v-col cols="6" pr-2>
					<v-text-field :model-value="defaults.email" @update:model-value="defaults.email = $event" label="Default Email" required></v-text-field>
					</v-col>
					<v-col cols="6" pl-2>
					<v-autocomplete :items="timezones" label="Default Timezone" v-model="defaults.timezone"></v-autocomplete>
					</v-col>
				</v-row>
				<v-row wrap>
					<v-col>
					<v-autocomplete label="Default Recipes" v-model="defaults.recipes" ref="default_recipes" :items="recipes" item-title="title" item-value="recipe_id" multiple chips closable-chips></v-autocomplete>
					</v-col>
				</v-row>
				<span class="body-2">Default Users</span>
				<v-data-table :items="defaults.users" hide-default-header hide-default-footer v-if="typeof defaults.users == 'object'">
				<template v-slot:body="{ items }">
					<tr v-for="(item, index) in items" style="border-top: 0px;">
						<td class="pa-1">
						<v-text-field variant="underlined" :model-value="item.username" @update:model-value="defaults.users[index].username = $event" label="Username"></v-text-field>
						</td>
						<td class="pa-1">
						<v-text-field variant="underlined" :model-value="item.email" @update:model-value="defaults.users[index].email = $event" label="Email"></v-text-field>
						</td>
						<td class="pa-1">
						<v-text-field variant="underlined" :model-value="item.first_name" @update:model-value="defaults.users[index].first_name = $event" label="First Name"></v-text-field>
						</td>
						<td class="pa-1">
						<v-text-field variant="underlined" :model-value="item.last_name" @update:model-value="defaults.users[index].last_name = $event" label="Last Name"></v-text-field>
						</td>
						<td class="pa-1" style="width:145px;">
						<v-select variant="underlined" :model-value="item.role" v-model="defaults.users[index].role" :items="roles" label="Role" item-title="name"></v-select>
						</td>
						<td class="pa-1" style="width: 60px;">
							<v-btn variant="text" icon="mdi-delete" color="primary" @click="deleteGlobalUserValue( index )"></v-btn>
						</td>
					</tr>
				</template>
				</v-data-table>

				<v-row>
				<v-col cols="12">
					<v-btn variant="tonal" size="small" @click="addGlobalDefaultsUser()" class="mt-3">Add Additional User</v-btn>
				</v-col>
				<v-col cols="12">
					<v-btn color="primary" @click="saveGlobalDefaults()">
					Save Changes
					</v-btn>
				</v-col>
				</v-card-text>

			</v-card>
			<v-card v-if="route == 'keys'" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Your SSH keys</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-btn variant="text" @click="new_key.show = true" v-show="role == 'administrator'">
						Add Management SSH Key <v-icon>mdi-plus</v-icon>
					</v-btn>
					<v-btn variant="text" @click="new_key_user.show = true">
						Add SSH Key <v-icon>mdi-plus</v-icon>
					</v-btn>
				</v-toolbar>
				<v-card-text>
					<v-container fluid>
						<v-alert type="info" variant="text" class="mb-4">
							<v-row>
								<v-col>
									It's recommended to use SSH keys for SFTP and SSH access instead of passwords. If you don't already have a key pair then <a href="https://docs.digitalocean.com/products/droplets/how-to/add-ssh-keys/create-with-openssh/" target="_blank" rel="noopener noreferrer">read this article on creating SSH keys</a>. Next add your public key here. Then use your private SSH key when connecting over SFTP or SSH instead of your password.
								</v-col>
							</v-row>
						</v-alert>
						
						<v-row>
							<v-col cols="12" v-for="key in keys" :key="key.key_id" class="py-2">
								<v-card hover @click="viewKey(key.key_id)">
									<v-card-title class="pt-2">
										<span class="text-h6">{{ key.title }}</span>
										<v-chip v-show="key.main == '1'" color="primary" class="ml-2">Primary Key</v-chip>
									</v-card-title>
									<v-card-text>
										<v-chip color="blue-grey-darken-1">{{ key.fingerprint }}</v-chip>
									</v-card-text>
								</v-card>
							</v-col>
						</v-row>
					</v-container>
				</v-card-text>
			</v-card>
			<v-card v-if="route == 'archives'" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent" class="pr-3">
					<v-toolbar-title>
						Listing {{ archives.length }} archives
						<small class="text-caption text-grey ml-2" v-if="archives.length > 0">
							({{ formatSize(totalArchivesSize) }})
						</small>
					</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-text-field 
						v-model="archive_search" 
						append-inner-icon="mdi-magnify" 
						label="Search" 
						density="compact" 
						variant="outlined" 
						clearable 
						hide-details 
						flat 
						style="max-width:300px;">
					</v-text-field>
				</v-toolbar>
				<v-card-text>
					<v-data-table
						:headers="[
							{ title: 'Name', key: 'name' },
							{ title: 'Size', key: 'size', width: '120px' },
							{ title: 'Modified', key: 'mod_time', width: '220px' },
							{ title: 'Actions', key: 'actions', align: 'end', sortable: false, width: '150px' }
						]"
						:items="archives"
						:search="archive_search"
						:sort-by="[{ key: 'mod_time', order: 'desc' }]"
						:loading="archives_loading"
						:items-per-page="100"
						:items-per-page-options="[25, 50, 100, { title: 'All', value: -1 }]"
						density="comfortable"
						hover
					>
						<template v-slot:item.name="{ item }">
							<v-icon icon="mdi-file-zip-box-outline" size="small" class="mr-2"></v-icon>
							{{ item.raw ? item.raw.name : item.name }}
						</template>

						<template v-slot:item.size="{ item }">
							{{ formatSize(item.raw ? item.raw.size : item.size) }}
						</template>

						<template v-slot:item.mod_time="{ item }">
							{{ pretty_timestamp(item.raw ? item.raw.mod_time : item.mod_time) }}
						</template>

						<template v-slot:item.actions="{ item }">
							<v-btn 
								size="small" 
								variant="tonal" 
								color="primary" 
								prepend-icon="mdi-link"
								@click="generateArchiveLink(item.raw || item)"
							>
								Get Link
							</v-btn>
						</template>
					</v-data-table>
				</v-card-text>
				<v-dialog v-model="dialog_archive_link.show" max-width="600px">
					<v-card>
						<v-toolbar color="primary" flat>
							<v-toolbar-title>Share Archive</v-toolbar-title>
							<v-spacer></v-spacer>
							<v-btn icon="mdi-close" @click="dialog_archive_link.show = false"></v-btn>
						</v-toolbar>
						<v-card-text class="pt-4">
							<div v-if="dialog_archive_link.loading" class="text-center my-4">
								<v-progress-circular indeterminate color="primary"></v-progress-circular>
								<p class="mt-2">Generating public link...</p>
							</div>
							<div v-else>
								<p class="mb-2">Public download link (valid for 7 days):</p>
								<v-text-field 
									v-model="dialog_archive_link.url" 
									readonly 
									variant="outlined"
									append-inner-icon="mdi-content-copy"
									@click:append-inner="copyText(dialog_archive_link.url)"
								></v-text-field>
							</div>
						</v-card-text>
						<v-card-actions>
							<v-spacer></v-spacer>
							<v-btn variant="text" @click="dialog_archive_link.show = false">Close</v-btn>
						</v-card-actions>
					</v-card>
				</v-dialog>
			</v-card>
			<v-card v-if="route == 'profile'" flat border="thin" rounded="xl" class="mx-auto" max-width="700">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Edit profile</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items></v-toolbar-items>
				</v-toolbar>
				<v-card-text>
					<v-row>
					<v-col cols="12">
						<v-list>
						<v-list-item link href="https://gravatar.com" target="_blank" title="Edit thumbnail with Gravatar" append-icon="mdi-open-in-new" density="compact">
							<template v-slot:prepend>
							<v-avatar rounded="lg"><v-img :src="gravatar"></v-img></v-avatar>
							</template>
						</v-list-item>
						</v-list>
						<v-text-field v-model="profile.display_name" label="Display Name" variant="underlined"></v-text-field>
						<v-text-field v-model="profile.email" label="Email" variant="underlined"></v-text-field>
						<v-text-field v-model="profile.new_password" type="password" label="New Password" hint="Leave empty to keep current password." persistent-hint variant="underlined"></v-text-field>
						<div class="mb-5"></div>
						<v-btn @click="disableTFA()" class="mb-7" v-if="profile.tfa_enabled" color="primary" variant="outlined">Turn off Two-Factor Authentication</v-btn>
						<v-btn @click="enableTFA()" class="mb-7" v-else color="primary">Enable Two-Factor Authentication</v-btn>
						<v-card v-show="profile.tfa_activate" flat border="thin" rounded="xl">
						<v-card-text>
							<v-row>
								<v-col class="text-center align-self-center" cols="7">
									<p>Scan the QR code with your password application and enter 6 digit code. Advanced users can manually complete using <a :href="profile.tfa_uri" target="_blank">this link</a> or <a href="#copyToken" @click="copyText( profile.tfa_token )" >token</a>.</p>
								</v-col>
								<v-col>
									<div id="tfa_qr_code" style="margin:auto;text-align:center;"></div>
								</v-col>
							</v-row>
							<v-text-field label="One time code" class="mt-3" v-model="login.tfa_code" variant="outlined"></v-text-field>
						</v-card-text>
						<v-card-actions>
							<v-btn @click="cancelTFA()" variant="flat">Cancel</v-btn>
							<v-spacer></v-spacer>
							<v-btn color="primary" @click="activateTFA()" variant="tonal">Activate Two-Factor Authenticate</v-btn>
						</v-card-actions>
						</v-card>
					</v-col>
					</v-row>
					<v-row>
					<v-col cols="12" class="mt-3">
						<v-alert variant="tonal" type="error" v-for="error in profile.errors" class="mt-5">{{ error }}</v-alert>
						<v-alert variant="tonal" type="success" v-show="profile.success" class="mt-5">{{ profile.success }}</v-alert>
						<v-btn color="primary" @click="updateAccount()">Save Account</v-btn>
					</v-col>
					</v-row>
				</v-card-text>
			</v-card>
			<v-card v-show="role == 'administrator' && route == 'subscriptions'" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Listing {{ subscriptions.length }} subscriptions</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-tooltip location="top">
						<template v-slot:activator="{ props }">
							<v-btn icon="mdi-poll" @click="toggle_plan = !toggle_plan" v-bind="props" variant="text"></v-btn>
						</template>
						<span>View reports</span>
					</v-tooltip>
				</v-toolbar>
				
				<v-data-table
					:headers="[
						{ title: 'Name', key: 'name' },
						{ title: 'Interval', key: 'interval' },
						{ title: 'Next Renewal', key: 'next_renewal' },
						{ title: 'Price', key: 'total', width: '100px' }]"
					:items="subscriptions"
					:search="subscription_search"
					:items-per-page="100"
					:items-per-page-options="[100,250,500,{'title':'All','value':-1}]"
					v-show="toggle_plan == true"
					hover
					@click:row="(event, { item }) => goToPath(`/subscription/${item.account_id}`)"
					style="cursor:pointer;"
				>
					<template v-slot:top>
						<v-card-text>
							<v-row>
								<v-col></v-col>
								<v-col cols="12" md="4">
									<v-text-field 
										class="mx-4" 
										v-model="subscription_search" 
										autofocus 
										append-inner-icon="mdi-magnify" 
										label="Search" 
										single-line 
										clearable 
										hide-details
										variant="underlined"
									></v-text-field>
								</v-col>
							</v-row>
						</v-card-text>
					</template>

					<template v-slot:item.interval="{ item }">
						{{ intervalLabel( item.interval ) }}
					</template>

					<template v-slot:item.total="{ item }">
						${{ item.total }}
					</template>
				</v-data-table>

				<div id="plan_chart"></div>
				<v-list-subheader>{{ revenue_estimated_total() }}</v-list-subheader>
				<div id="plan_chart_transactions"></div>
			</v-card>
			<v-card v-if="route == 'accounts'" flat border="thin" rounded="xl">
			<v-sheet v-show="dialog_account.step == 1" color="transparent">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Listing {{ accounts.length }} accounts</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items v-if="role == 'administrator'">
						<v-btn variant="text" @click="dialog_new_account.show = true">Add account <v-icon dark>mdi-plus</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-row align="center" justify="end" class="mb-4 mx-1">
    
					<v-col cols="12" md="auto" class="d-flex flex-wrap align-center justify-end gap-2">
    
				<!-- Outstanding Invoices Filter -->
				<v-btn
					:variant="isOutstandingFilterActive ? 'flat' : 'text'"
					:color="isOutstandingFilterActive ? 'error' : 'medium-emphasis'"
					size="small"
					rounded="pill"
					@click="toggleOutstandingFilter()"
					v-if="role == 'administrator'"
					class="mr-2 mb-1"
					title="Show accounts with unpaid invoices"
				>
					<v-icon start icon="mdi-cash-remove"></v-icon>
					{{ oustandingAccountCount }} Outstanding
				</v-btn>

				<!-- Empty Accounts Filter -->
				<v-btn
					:variant="isEmptyFilterActive ? 'flat' : 'text'"
					:color="isEmptyFilterActive ? 'warning' : 'medium-emphasis'"
					size="small"
					rounded="pill"
					@click="toggleEmptyFilter()"
					v-if="role == 'administrator'"
					class="mr-2 mb-1"
					title="Show accounts with no sites, domains, or users"
				>
					<v-icon start icon="mdi-inbox-remove-outline"></v-icon>
					{{ emptyAccountCount }} Empty
				</v-btn>

				<!-- Clear Filters (Animated entry) -->
				<v-slide-x-transition>
					<v-btn
						v-if="isAnyAccountFilterActive"
						icon="mdi-filter-off"
						size="small"
						variant="text"
						color="medium-emphasis"
						class="mr-2 mb-1"
						@click="clearAccountFilters()"
						title="Clear all filters"
					>
					</v-btn>
				</v-slide-x-transition>

			</v-col>

					<v-col cols="12" md="4" lg="3">
						<v-text-field 
							variant="outlined" 
							density="compact" 
							v-model="account_search" 
							autofocus 
							label="Search" 
							clearable 
							hide-details 
							append-inner-icon="mdi-magnify"
						></v-text-field> 
					</v-col>
				</v-row>
				<v-card-text>
					<v-data-table
						:headers="[
							{ title: 'Name', value: 'name' },
							{ title: 'Users', value: 'metrics.users', width: '100px' },
							{ title: 'Sites', value: 'metrics.sites', width: '100px' },
							{ title: 'Domains', value: 'metrics.domains', width: '100px' }]"
						:items="filteredAccountsData"
						:search="account_search"
						:items-per-page="100"
						:items-per-page-options="[100,250,500,{'title':'All','value':-1}]"
						item-value="account_id"
						hover
						density="comfortable"
						@click:row="(event, { item }) => goToPath(`/accounts/${item.account_id}`)"
						class="clickable-rows"
					>
						<template v-slot:item.metrics.users="{ value }">
						<span v-if="value != null && value !== ''">{{ value }}</span>
						</template>
						<template v-slot:item.metrics.sites="{ value }">
						<span v-if="value != null && value !== ''">{{ value }}</span>
						</template>
						<template v-slot:item.metrics.domains="{ value }">
						<span v-if="value != null && value !== ''">{{ value }}</span>
						</template>

					</v-data-table>
					</v-card-text>
				</v-sheet>
				<v-sheet v-show="dialog_account.step == 2" color="transparent">
				<div v-if="dialog_account.loading" class="text-center pa-10">
					<v-progress-circular indeterminate color="primary"></v-progress-circular>
				</div>
				<v-card flat v-else-if="dialog_account.show && typeof dialog_account.records.account == 'object'" rounded="xl">
					<v-toolbar flat color="transparent">
						<v-toolbar-title>{{ dialog_account.records.account.name }}</v-toolbar-title>
					</v-toolbar>
					<v-container class="pt-0">
					<v-toolbar color="primary" dark flat density="compact" rounded="lg">
					<v-tabs v-model="account_tab" density="compact" left hide-slider>
						<v-tab>
							{{ dialog_account.records.users.length }} Users
							<v-icon size="20" class="ml-1">mdi-account</v-icon>
						</v-tab>
						<v-tab>
							{{ dialog_account.records.sites.length }} Sites
							<v-icon size="20" class="ml-1">mdi-folder-multiple</v-icon>
						</v-tab>
						<v-tab>
							{{ dialog_account.records.domains.length }} Domains
							<v-icon size="20" class="ml-1">mdi-text-box-multiple</v-icon>
						</v-tab>
						<v-tab>
							Timeline
							<v-icon size="20" class="ml-1">mdi-timeline-text-outline</v-icon>
						</v-tab>
						<v-tab v-show="role == 'administrator'">
							Invoices <v-icon size="20" class="ml-1">mdi-receipt-text</v-icon>
						</v-tab>
						<v-tab v-show="role == 'administrator' || dialog_account.records.owner">
							Plan <v-icon size="20" class="ml-1">mdi-chart-donut</v-icon>
						</v-tab>
					</v-tabs>
					</v-toolbar>
					<v-window v-model="account_tab">
					<v-window-item :transition="false" :reverse-transition="false">
						<v-toolbar density="compact" flat color="transparent">
							<div class="flex-grow-1"></div>
							<v-toolbar-items>
							<v-dialog v-model="dialog_account.new_invite" max-width="500px">
							<template v-slot:activator="{ props }">
								<v-btn variant="text" @click="dialog_account.new_invite = true" v-bind="props">New Invite <v-icon dark>mdi-plus</v-icon></v-btn>
							</template>
							<v-card>
								<v-toolbar flat density="compact" dark color="primary" id="new_invite" class="mb-2">
								<v-btn icon dark @click.native="dialog_account.new_invite = false">
									<v-icon>mdi-close</v-icon>
								</v-btn>
								<v-toolbar-title>New Invitation</v-toolbar-title>
								<v-spacer></v-spacer>
								</v-toolbar>
								<v-card-text>
								<v-row>
									<v-col cols="12">
										<v-text-field variant="underlined" label="Email" :model-value="dialog_account.new_invite_email" @update:model-value="dialog_account.new_invite_email = $event"></v-text-field>
									</v-col>
									<v-col cols="12">
										<v-btn color="primary" dark @click="sendAccountInvite()">
											Send Invite
										</v-btn>
									</v-col>
								</v-row>
								</v-card-text>
							</v-card>
							</v-dialog>
							</v-toolbar-items>
						</v-toolbar>
							<v-data-table
								v-show="typeof dialog_account.records.users == 'object' && dialog_account.records.users.length > 0"
								:headers='[{"title":"Name","value":"name"},{"title":"Email","value":"email"},{"title":"","value":"level"},{"title":"","value":"actions"}]'
								:items="dialog_account.records.users"
								:sort-by='["level","name"]'
								sort-desc
								:items-per-page="-1"
								hide-default-footer
							>
							<template v-slot:item.actions="{ item }">
							<v-btn variant="text" icon color="pink" @click="removeAccountAccess( item.user_id )" v-if="role == 'administrator' || dialog_account.records.owner && item.level != 'Owner'">
								<v-icon>mdi-delete</v-icon>
							</v-btn>
							</template>
							</v-data-table>
							<v-data-table
								v-show="typeof dialog_account.records.invites == 'object' && dialog_account.records.invites.length > 0"
								:headers='[{"title":"Email","value":"email"},{"title":"Created","value":"created_at"},{"title":"","value":"actions"}]'
								:items="dialog_account.records.invites"
								:items-per-page="-1"
								hide-default-footer
								hide-default-header
							>
							<template v-slot:header>
								<tr>
								<td colspan="3" style="padding:0px;padding-top:16px;">
									<v-divider></v-divider>
									<v-list-subheader>Invites</v-list-subheader>
								</td>
								</tr>
							</template>
							<template v-slot:item.created_at="{ item }">
							{{ pretty_timestamp( item.created_at ) }}
							</template>
							<template v-slot:item.actions="{ item }">
							<v-tooltip location="top">
								<template v-slot:activator="{ props }">
									<v-btn variant="text" icon v-bind="props" @click="copyInviteLink( item.account_id, item.token )"><v-icon dark>mdi-link-variant</v-icon></v-btn>
								</template><span>Copy Invite Link</span>
							</v-tooltip>
							<v-tooltip location="top">
								<template v-slot:activator="{ props }">
									<v-btn variant="text" icon color="pink" @click="deleteInvite( item.invite_id )" v-bind="props"><v-icon dark>mdi-delete</v-icon></v-btn>
								</template><span>Delete Invite</span>
							</v-tooltip>
							</template>
							</v-data-table>
					</v-window-item>
					<v-window-item :transition="false" :reverse-transition="false">
							<v-data-table
								v-show="typeof dialog_account.records.sites == 'object' && dialog_account.records.sites.length > 0"
								:headers='[{"title":"Sites","value":"name"},{"title":"Storage","value":"storage"},{"title":"Visits","value":"visits"},{"title":"","value":"actions","width":"110px",sortable: false}]'
								:items="dialog_account.records.sites"
								:items-per-page="-1"
								hide-default-footer
							>
							<template v-slot:item.storage="{ item }">
								{{ formatGBs( item.storage ) }}GB
							</template>
							<template v-slot:item.visits="{ item }">
								{{ formatLargeNumbers( item.visits ) }}
							</template>
							<template v-slot:item.actions="{ item }">
								<v-btn size="small" variant="tonal" @click="goToPath( `/sites/${item.site_id}` )">View</v-btn>
							</template>
							<template v-slot:body.append>
								<tr>
								<td class="text-right">
									Totals: 
								</td>
								<td>
									{{ formatGBs( dialog_account.records.account.plan.usage.storage ) }}GB
								</td>
								<td>
									{{ formatLargeNumbers( dialog_account.records.account.plan.usage.visits ) }}
								</td>
								</tr>
							</template>
							</v-data-table>
					</v-window-item>
					<v-window-item :transition="false" :reverse-transition="false">
						<v-data-table
							v-show="typeof dialog_account.records.domains == 'object' && dialog_account.records.domains.length > 0"
							:headers='[{"title":"Domain","value":"name"},{"title":"","value":"actions","width":"110px",sortable:false}]'
							:items="dialog_account.records.domains"
							:items-per-page="-1"
							hide-default-footer
						>
						<template v-slot:item.actions="{ item }">
							<v-btn size="small" variant="tonal" @click="goToPath( `/domains/${item.domain_id}` )">View</v-btn>
						</template>
						</v-data-table>
					</v-window-item>
					<v-window-item :transition="false" :reverse-transition="false">
						<v-data-table :headers="header_timeline" :items="dialog_account.records.timeline" item-value="process_log_id" :items-per-page-options="[50, 100, 250, { title: 'All', value: -1 }]" :items-per-page="50" class="timeline">
							<template v-slot:item="{ item }">
								<tr>
									<td class="pt-3 pr-0 text-center shrink" style="vertical-align: top;">
										<v-tooltip location="bottom">
											<template v-slot:activator="{ props }">
												<v-icon v-if="item.name" color="primary" v-bind="props" icon="mdi-note"></v-icon>
												<v-icon v-else color="primary" icon="mdi-checkbox-marked-circle"></v-icon>
											</template>
											<span>{{ item.name }}</span>
										</v-tooltip>
									</td>
									<td class="py-4" style="vertical-align: top;">
										<div v-if="item.description" v-html="item.description"></div>
									</td>
									<td class="pt-2" style="vertical-align: top; width: 180px;">
										<v-row align="center" no-gutters>
											<v-col cols="auto" class="pr-2">
												<v-avatar :image="item.author_avatar" size="34" rounded></v-avatar>
											</v-col>
											<v-col>
												<div class="text-no-wrap">{{ item.author }}</div>
											</v-col>
										</v-row>
									</td>
									<td class="pt-3" style="vertical-align: top;">{{ pretty_timestamp_epoch( item.created_at ) }}</td>
									<td class="pa-0" style="vertical-align: top;">
										<v-menu :nudge-width="200" open-on-hover location="bottom">
											<template v-slot:activator="{ props }">
												<v-icon class="my-2" v-bind="props" size="small" icon="mdi-information"></v-icon>
											</template>
											<v-card>
												<v-card-text>
													<div v-for="site in item.websites" :key="site.site_id">
														<a :href="`${configurations.path}sites/${site.site_id}`" @click.prevent="goToPath(`/sites/${site.site_id}`)">{{ site.name }}</a>
													</div>
												</v-card-text>
											</v-card>
										</v-menu>
										<v-btn v-if="role === 'administrator'" @click="dialog_log_history.show = false; editLogEntry(item.websites, item.process_log_id)" variant="text" icon="mdi-pencil" size="small"></v-btn>
									</td>
								</tr>
							</template>
						</v-data-table>
					</v-window-item>
					<v-window-item :transition="false" :reverse-transition="false">
						<v-data-table
							:headers="[
								{ title: 'Order', key: 'order_id', width: '130px' },
								{ title: 'Date', key: 'date', width: '170px' },
								{ title: 'Name', key: 'name' },
								{ title: 'Status', key: 'status' },
								{ title: 'Total', key: 'total', width: '120px' }]"
							:items="dialog_account.records.invoices || []"
							:items-per-page="100"
							:items-per-page-options="[100,250,500,{'title':'All','value':-1}]"
						>
							<template v-slot:item.order_id="{ item }">
								#{{ item.order_id }}
							</template>
							<template v-slot:item.total="{ item }">
								${{ item.total }}
							</template>
						</v-data-table>
					</v-window-item>
					<v-window-item :transition="false" :reverse-transition="false">
						<v-toolbar density="compact" color="transparent" flat>
							<v-spacer></v-spacer>
							<div v-show="role == 'administrator'">
								<v-btn variant="text" @click="modifyPlan()">Edit Plan <v-icon size="small" class="ml-1">mdi-pencil</v-icon></v-btn>
							</div>
						</v-toolbar>
						<v-card flat>
							<div v-if="typeof dialog_account.records.account.plan == 'object' && dialog_account.records.account.plan != null && dialog_account.records.account.plan.next_renewal">
								<v-card-text class="text-body-1">
									<v-row>
										<v-col>
											<v-row align="center" no-gutters>
												<v-col cols="auto" class="pa-2 d-flex align-center">
													<template v-if="dialog_account.records.account.plan.billing_mode === 'per_site'">
														<v-avatar variant="tonal" color="primary" size="50">
															<v-icon>mdi-database</v-icon>
														</v-avatar>
														<div class="ml-2" style="line-height: 1.2em;">
															Storage <br /><small>{{ formatGBs( dialog_account.records.account.plan.usage.storage ) }}GB</small>
														</div>
													</template>
													<template v-else>
														<v-progress-circular :size="50" :model-value="formatPercentage (( dialog_account.records.account.plan.usage.storage / ( dialog_account.records.account.plan.limits.storage * 1024 * 1024 * 1024 ) ) * 100 )" color="primary"><span v-html="account_storage_percentage( dialog_account.records.account )"></span></v-progress-circular>
														<div class="ml-2" style="line-height: 1.2em;">
															Storage <br /><small>{{ formatGBs( dialog_account.records.account.plan.usage.storage ) }}GB / {{ dialog_account.records.account.plan.limits.storage }}GB</small>
														</div>
													</template>
												</v-col>
												<v-col cols="auto" class="pa-2 d-flex align-center">
													<template v-if="dialog_account.records.account.plan.billing_mode === 'per_site'">
														<v-avatar variant="tonal" color="primary" size="50">
															<v-icon>mdi-chart-bar</v-icon>
														</v-avatar>
														<div class="ml-2" style="line-height: 1.2em;">
															Visits <br /><small>{{ formatLargeNumbers( dialog_account.records.account.plan.usage.visits ) }}</small>
														</div>
													</template>
													<template v-else>
														<v-progress-circular :size="50" :model-value="formatPercentage (( dialog_account.records.account.plan.usage.visits / dialog_account.records.account.plan.limits.visits * 100 ) )" color="primary"><span v-html="account_visits_percentage( dialog_account.records.account )"></span></v-progress-circular>
														<div class="ml-2" style="line-height: 1.2em;">
															Visits <br /><small>{{ formatLargeNumbers( dialog_account.records.account.plan.usage.visits ) }} / {{ formatLargeNumbers( dialog_account.records.account.plan.limits.visits ) }}</small>
														</div>
													</template>
												</v-col>
												<v-col cols="auto" class="pa-2 d-flex align-center">
													<template v-if="dialog_account.records.account.plan.billing_mode === 'per_site'">
														<v-avatar variant="tonal" color="blue-darken-4" size="50">
															<v-icon>mdi-web</v-icon>
														</v-avatar>
														<div class="ml-2" style="line-height: 1.2em;">
															Sites <br /><small>{{ dialog_account.records.account.plan.usage.sites }} Active</small>
														</div>
													</template>
													<template v-else>
														<v-progress-circular :size="50" :model-value="formatPercentage(( dialog_account.records.account.plan.usage.sites / dialog_account.records.account.plan.limits.sites * 100 ) )" color="blue-darken-4"><span v-html="account_site_percentage( dialog_account.records.account )"></span></v-progress-circular>
														<div class="ml-2" style="line-height: 1.2em;">
															Sites <br /><small>{{ dialog_account.records.account.plan.usage.sites }} / {{ dialog_account.records.account.plan.limits.sites }}</small>
														</div>
													</template>
												</v-col>
											</v-row>
										</v-col>
										<v-col class="text-center">
											<span class="text-uppercase text-caption">Next Renewal Estimate</span>
											<v-tooltip location="bottom">
												<template v-slot:activator="{ props }">
													<v-icon class="ml-1" v-bind="props">mdi-calendar</v-icon>
												</template>
												<span>Renews on {{ pretty_timestamp_short( dialog_account.records.account.plan.next_renewal ) }}</span>
											</v-tooltip><br />
											<span class="text-h4 font-weight-thin" v-html="plan_usage_estimate"></span><br />
											<v-dialog v-model="dialog_breakdown" max-width="980px">
												<template v-slot:activator="{ props }">
													<a v-bind="props" style="cursor: pointer;">See breakdown</a>
												</template>
												<v-card>
													<v-toolbar flat color="primary">
														<v-btn icon="mdi-close" @click="dialog_breakdown = false"></v-btn>
														<v-toolbar-title>Plan Estimate Breakdown</v-toolbar-title>
													</v-toolbar>
													<v-card-text>
														<div class="v-table v-table--has-top v-table--has-bottom v-table--density-default v-data-table mb-3">
														<div class="v-table__wrapper">
														<table>
															<thead>
																<tr>
																	<th class="text-left">Type</th>
																	<th class="text-left">Name</th>
																	<th class="text-left">Quantity</th>
																	<th class="text-right">Price</th>
																	<th class="text-right">Total</th>
																</tr>
															</thead>
															<tbody>
																<tr v-if="dialog_account.records.account.plan.billing_mode === 'per_site'">
																	<td>Plan</td>
																	<td>Per Site</td>
																	<td>{{ dialog_account.records.account.plan.usage.sites }}</td>
																	<td class="text-right">${{ dialog_account.records.account.plan.price }}</td>
																	<td class="text-right">${{ (dialog_account.records.account.plan.usage.sites * dialog_account.records.account.plan.price).toFixed(2) }}</td>
																</tr>
																<tr v-else>
																	<td>Plan</td>
																	<td>{{ dialog_account.records.account.plan.name }}</td>
																	<td>1</td>
																	<td class="text-right">${{ dialog_account.records.account.plan.price }}</td>
																	<td class="text-right">${{ dialog_account.records.account.plan.price }}</td>
																</tr>
																<tr v-if="( parseInt( dialog_account.records.account.plan.usage.sites ) - parseInt( dialog_account.records.account.plan.limits.sites ) ) >= 1">
																	<td>Extra</td>
																	<td>Sites</td>
																	<td>{{ parseInt( dialog_account.records.account.plan.usage.sites ) - parseInt( dialog_account.records.account.plan.limits.sites ) }}</td>
																	<td class="text-right">${{ plan_usage_pricing_sites }}</td>
																	<td class="text-right">${{ plan_usage_pricing_sites * ( parseInt( dialog_account.records.account.plan.usage.sites ) - parseInt( dialog_account.records.account.plan.limits.sites ) ) }}</td>
																</tr>
																<tr v-if="(( parseInt( dialog_account.records.account.plan.usage.storage ) / 1024 / 1024 / 1024 ) - parseInt( dialog_account.records.account.plan.limits.storage ) ) >= 1">
																	<td>Extra</td>
																	<td>Storage</td>
																	<td>{{ Math.ceil ( ( ( parseInt( dialog_account.records.account.plan.usage.storage ) / 1024 / 1024 / 1024 ) - parseInt( dialog_account.records.account.plan.limits.storage ) ) / 10 ) }}</td>
																	<td class="text-right">${{ plan_usage_pricing_storage }}</td>
																	<td class="text-right">${{ plan_usage_pricing_storage * Math.ceil ( ( ( parseInt( dialog_account.records.account.plan.usage.storage ) / 1024 / 1024 / 1024 ) - parseInt( dialog_account.records.account.plan.limits.storage ) ) / 10 ) }}</td>
																</tr>
																<tr v-if="Math.ceil ( ( parseInt( dialog_account.records.account.plan.usage.visits ) - parseInt( dialog_account.records.account.plan.limits.visits ) ) / parseInt ( configurations.usage_pricing.traffic.quantity ) ) >= 1">
																	<td>Extra</td>
																	<td>Visits</td>
																	<td>{{ Math.ceil ( ( parseInt( dialog_account.records.account.plan.usage.visits ) - parseInt( dialog_account.records.account.plan.limits.visits ) ) / parseInt ( configurations.usage_pricing.traffic.quantity ) ) }}</td>
																	<td class="text-right">${{ plan_usage_pricing_visits }}</td>
																	<td class="text-right">${{ plan_usage_pricing_visits * Math.ceil ( ( parseInt( dialog_account.records.account.plan.usage.visits ) - parseInt( dialog_account.records.account.plan.limits.visits ) ) / parseInt ( configurations.usage_pricing.traffic.quantity ) ) }}</td>
																</tr>
																<tr v-for="item in dialog_account.records.account.plan.addons">
																	<td>Addon</td>
																	<td>{{ item?.name }}</td>
																	<td>{{ item?.quantity }}</td>
																	<td class="text-right">${{ item?.price }}</td>
																	<td class="text-right">${{ ( item?.quantity * item?.price ).toFixed(2) }}</td>
																</tr>
																<tr v-for="item in dialog_account.records.account.plan.charges">
																	<td>Charge</td>
																	<td>{{ item?.name }}</td>
																	<td>{{ item?.quantity }}</td>
																	<td class="text-right">${{ item?.price }}</td>
																	<td class="text-right">${{ ( item?.quantity * item?.price ).toFixed(2) }}</td>
																</tr>
																<tr v-for="item in dialog_account.records.account.plan.credits">
																	<td>Credit</td>
																	<td>{{ item?.name }}</td>
																	<td>{{ item?.quantity }}</td>
																	<td class="text-right">-${{ item?.price }}</td>
																	<td class="text-right">-${{ ( item?.quantity * item?.price ).toFixed(2) }}</td>
																</tr>
																<tr>
																	<td colspan="5" class="text-body-1">Total: <span v-html="plan_usage_estimate"></span></td>
																</tr>
															</tbody>
														</table>
														</div>
														</div>
													</v-card-text>
												</v-card>
											</v-dialog>
										</v-col>
									</v-row>
								</v-card-text>
								<v-alert variant="tonal" type="info" class="mx-2">
									<strong>{{ dialog_account.records.account.plan.name }} Plan</strong> supports up to {{ formatLargeNumbers( dialog_account.records.account.plan.limits.visits ) }} visits, {{ dialog_account.records.account.plan.limits.storage }}GB storage and {{ dialog_account.records.account.plan.limits.sites }} sites. Extra sites, storage and visits charged based on usage.
								</v-alert>
								<v-data-table
									:headers='[
										{"title":"Name","key":"name"},
										{"title":"Storage","key":"storage"},
										{"title":"Visits","key":"visits"},
										{"title":"","key":"actions", "width":"110px", "sortable": false}
									]'
									:items="dialog_account.records.usage_breakdown.sites || []"
									item-value="name"
									:items-per-page="-1"
									hide-default-footer
									class="mb-3"
								>
									<template v-slot:item.storage="{ item }">
										{{ formatGBs(item.storage) }}GB
									</template>

									<template v-slot:item.actions="{ item }">
										<v-btn variant="tonal" size="small" @click="goToPath(`/sites/${item.site_id}`)">View</v-btn>
									</template>

									<template v-slot:tfoot>
										<tfoot>
											<tr>
												<td><strong>Totals:</strong></td>
												
												<td v-for="(total, index) in dialog_account.records.usage_breakdown.total || []" :key="index" v-html="total"></td>

												<td></td>
											</tr>
										</tfoot>
									</template>
								</v-data-table>
								<v-alert variant="tonal" type="info" class="mx-2" v-if="dialog_account.records.usage_breakdown.maintenance_sites && dialog_account.records.usage_breakdown.maintenance_sites.length > 0">
									Includes {{ dialog_account.records.usage_breakdown.maintenance_sites.length }} connected sites. Connected sites are charged for management services only.
									<v-data-table
									:headers='[
										{"title":"Name","key":"name"},
										{"title":"Storage","key":"storage"},
										{"title":"Visits","key":"visits"},
										{"title":"","key":"actions","width":"110px", "sortable": false}
									]'
									:items="dialog_account.records.usage_breakdown.maintenance_sites || []"
									item-value="name"
									:items-per-page="-1"
									hide-default-footer
								>
									<template v-slot:item.storage="{ item }">
										{{ formatGBs(item.storage) }}GB
									</template>

									<template v-slot:item.actions="{ item }">
										<v-btn variant="tonal" size="small" @click="goToPath(`/sites/${item.site_id}`)">View</v-btn>
									</template>
								</v-data-table>
								</v-alert>
							</div>
							<div v-else>
								<v-alert variant="tonal" type="info" color="primary" class="text-body-1 ma-2">
									Hosting plan not active.
								</v-alert>
							</div>
						</v-card>
					</v-window-item>

					</v-window>
				</v-container>
				</v-sheet>
			</v-card>
			<v-card v-if="route == 'users'" flat border="thin" rounded="xl">
				<v-toolbar flat color="transparent">
					<v-toolbar-title>Listing {{ users.length }} users</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
						<v-dialog max-width="600">
							<template v-slot:activator="{ props }">
								<v-btn variant="text" v-bind="props">
									Add user <v-icon dark>mdi-plus</v-icon>
								</v-btn>
							</template>
							<template v-slot:default="{ isActive }">
								<v-card>
									<v-toolbar color="primary" dark>
										<v-btn icon dark @click="isActive.value = false">
											<v-icon>mdi-close</v-icon>
										</v-btn>
										<v-toolbar-title>Add user</v-toolbar-title>
										<v-spacer></v-spacer>
									</v-toolbar>
									<v-card-text class="pt-3">
										<v-row>
											<v-col>
												<v-text-field v-model="dialog_new_user.first_name" label="First Name" variant="underlined"></v-text-field>
											</v-col>
											<v-col>
												<v-text-field v-model="dialog_new_user.last_name" label="Last Name" variant="underlined"></v-text-field>
											</v-col>
										</v-row>
										<v-text-field v-model="dialog_new_user.email" label="Email" variant="underlined"></v-text-field>
										<v-text-field v-model="dialog_new_user.login" label="Username" variant="underlined"></v-text-field>
										<v-autocomplete 
											:items="accounts" 
											item-title="name" 
											item-value="account_id" 
											v-model="dialog_new_user.account_ids" 
											label="Accounts" 
											chips 
											multiple 
											closable-chips
											variant="underlined"
										></v-autocomplete>
										<v-alert variant="tonal" type="error" v-for="error in dialog_new_user.errors" class="mt-5">{{ error }}</v-alert>
										<v-col cols="12" class="mt-5">
											<v-btn color="primary" dark @click="newUser(isActive)">Create User</v-btn>
										</v-col>
									</v-card-text>
								</v-card>
							</template>
						</v-dialog>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text>
				<v-toolbar flat color="transparent">
					<v-spacer></v-spacer>
					<v-text-field class="mx-4" variant="outlined" density="compact" v-model="user_search" autofocus label="Search" clearable light hide-details append-inner-icon="mdi-magnify" style="max-width:300px;"></v-text-field>	
				</v-toolbar>
				<v-data-table
					:headers="[{ title: 'Name', key: 'name' },{ title: 'Username', key: 'username' },{ title: 'Email', key: 'email' },{ title: '', key: 'user_id', align: 'end', sortable: false }]"
					:items="users"
					:search="user_search"
					density="comfortable"
					:items-per-page="100"
					:items-per-page-options="[100,250,500,{'title':'All','value':-1}]"
				>
					<template v-slot:item.user_id="{ item }">
						<v-menu :nudge-width="200" open-on-hover location="bottom" @update:model-value="isOpen => fetchUserAccounts(item, isOpen)">
							<template v-slot:activator="{ props }">
								<v-icon class="my-2" v-bind="props" size="small" icon="mdi-information"></v-icon>
							</template>
							<v-card>
								<v-card-text>
									<div v-if="item._accounts_loading" class="text-center pa-2">
										<v-progress-circular indeterminate size="24"></v-progress-circular>
									</div>

									<div v-else-if="item._accounts_data">
										<div v-if="item._accounts_data.length === 0">
											No accounts assigned.
										</div>
										<div v-else v-for="account in item._accounts_data" :key="account.account_id">
											<a :href="`${configurations.path}accounts/${account.account_id}`" @click.prevent="goToPath(`/accounts/${account.account_id}`)">{{ account.name }}</a>
										</div>
									</div>
								</v-card-text>
							</v-card>
						</v-menu>
						<v-btn variant="text" color="primary" @click="editUser( item.user_id )" icon="mdi-pencil" size="small"></v-btn>
					</template>
				</v-data-table>
				</v-card-text>
			</v-card>
			<v-dialog v-if="route == 'invite'" value="true" scrollable persistance width="500" height="300">
			<v-overlay :value="true" v-if="typeof new_invite.account.name == 'undefined'">
				<v-progress-circular indeterminate size="64"></v-progress-circular>
			</v-overlay>
			<v-card tile v-else>
				<v-toolbar light flat>
					<v-toolbar-title>Account <strong><span v-html="new_invite.account.name"></span></strong> contains:</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
					</v-toolbar-items>
					<template v-slot:extension>
						<v-tabs v-model="account_tab" background-color="primary" dark>
						<v-tab>
							<v-icon class="mr-1">mdi-folder-multiple</v-icon>
							{{ new_invite.account.website_count }} Sites
						</v-tab>
						<v-tab>
							<v-icon class="mr-1">mdi-text-box-multiple</v-icon>
							{{ new_invite.account.domain_count }} Domains
						</v-tab>
						</v-tabs>
					</template>
				</v-toolbar>
				<v-card-text style="height:300px;">
					<v-window v-model="account_tab">
					<v-window-item>
						<v-data-table
							v-show="typeof new_invite.sites == 'object' && new_invite.sites.length > 0"
							:headers='[{"title":"Sites","value":"name"}]'
							:items="new_invite.sites"
							:items-per-page="-1"
							hide-default-footer
						>
						</v-data-table>
					</v-window-item>
					<v-window-item>
						<v-data-table
							v-show="typeof new_invite.domains == 'object' && new_invite.domains.length > 0"
							:headers='[{"title":"Domain","value":"name"}]'
							:items="new_invite.domains"
							:items-per-page="-1"
							hide-default-footer
						>
						</v-data-table>
					</v-window-item>
					</v-window>
						</v-card-text>
						<v-divider></v-divider>
						<v-card-actions>
							<div class="flex-grow-1"></div>
							<v-btn @click="cancelInvite">Cancel</v-btn>
							<v-btn @click="acceptInvite" color="primary" dark>Accept Invite as {{ current_user_login }}</v-btn>
						</v-card-actions>
						</v-card>
						</v-dialog>
					</v-layout>
				</v-container>
				</v-card-text>
			</v-card>
			<v-container v-if="route == 'sites' && role == 'administrator' && ! loading_page && dialog_site.step == 2" class="mt-5">
				<v-card color="transparent" density="compact" flat subtitle="Administrator Options">
					<template v-slot:actions>
						<v-btn size="small" variant="outlined" @click="showDomainMappings()" prepend-icon="mdi-dns" v-if="dialog_site.site.provider == 'kinsta' || dialog_site.site.provider == 'rocketdotnet'">
							Configure Domains
						</v-btn>
						<v-btn size="small" variant="outlined" @click="copySite(dialog_site.site.site_id)" prepend-icon="mdi-content-duplicate">
							Copy Site
						</v-btn>
						<v-btn size="small" variant="outlined" @click="editSite()" prepend-icon="mdi-pencil">
							Edit Site
						</v-btn>
						<v-btn size="small" variant="outlined" color="error" @click="deleteSite(dialog_site.site.site_id)" prepend-icon="mdi-delete">
							Delete Site
						</v-btn>
					</template>
				</v-card>
			</v-container>
			<v-container v-if="route == 'domains' && ! loading_page && dialog_domain.step == 2 && ! dialog_domain.loading" class="mt-5 pb-0">
			<v-list-subheader class="ml-4">Shared With</v-list-subheader>
			<v-container>
			<v-row density="compact" v-if="dialog_domain.accounts && dialog_domain.accounts.length > 0">
				<v-col v-for="account in dialog_domain.accounts" :key="account.account_id" cols="12" md="4">
				<v-card :href="role == 'administrator' ? `${configurations.path}accounts/${account.account_id}` : null" @click.prevent="role == 'administrator' ? goToPath( '/accounts/' + account.account_id ) : null" :disabled="role != 'administrator'" :ripple="role == 'administrator'" density="compact" flat border="thin" rounded="xl">
					<v-card-title class="text-body-1">
						<span v-html="account.name"></span>
					</v-card-title>
					<v-card-subtitle class="mb-3">Account #{{ account.account_id }}</v-card-subtitle>
				</v-card>
			</v-col>
			</v-row>
			</v-container>
			</v-container>
			<v-container v-if="route == 'domains' && ! loading_page && dialog_domain.step == 2 && ! dialog_domain.loading" class="mt-5">
				<v-card color="transparent" density="compact" flat subtitle="Domain Options">
					<template v-slot:actions>
						<v-btn 
							size="small" 
							variant="outlined"
							@click="dialog_domain.update_account.show = true">
							Update Account
						</v-btn>
						<v-btn 
							size="small" 
							variant="outlined" 
							@click="activateEmailForwarding(false)" 
							prepend-icon="mdi-email-arrow-right"
							:loading="dialog_domain.activating_forwarding"
							v-if="!dialog_domain.details.forward_email_id"
						>
							Activate Email Forwarding
						</v-btn>
						<v-chip
							v-if="dialog_domain.details.forward_email_id"
							color="primary"
							label
							variant="tonal"
							prepend-icon="mdi-check"
						>
							Email Forwarding is active
						</v-chip>
						<v-btn
							size="small"
							variant="outlined"
							@click="showMailgunActivatePrompt(dialog_domain.domain)"
							v-if="!dialog_domain.details.mailgun_id"
							:loading="mailgun.loadingActivate"
							:disabled="mailgun.loadingActivate"
						>
							<template v-slot:loader>
								<v-progress-circular indeterminate size="16" width="2" class="mx-2"></v-progress-circular>
								Activating Mailgun...
							</template>
							<v-icon left>mdi-rocket-launch</v-icon>
							Activate Mailgun
						</v-btn>
						<v-chip label color="primary" variant="tonal" prepend-icon="mdi-check" v-if="dialog_domain.details.mailgun_id">
							Mailgun zone created
						</v-chip>
					</template>
				</v-card>
			</v-container>
			<v-container v-if="route == 'domains' && role == 'administrator' && ! loading_page && dialog_domain.step == 2 && ! dialog_domain.loading">
				<v-card color="transparent" density="compact" flat subtitle="Administrator Options">
					<template v-slot:actions>
					<v-dialog max-width="600">
				<template v-slot:activator="{ props }">
					<v-btn v-bind="props" variant="outlined">Edit Domain</v-btn>
				</template>
				<template v-slot:default="{ isActive }">
				<v-card>
					<v-toolbar color="primary" dark>
					<v-btn icon="mdi-close" @click="isActive.value = false"></v-btn>
					<v-toolbar-title>Edit Domain</v-toolbar-title></v-toolbar>
					<v-card-text>
					<v-autocomplete
						v-model="dialog_domain.account_ids"
						multiple
						chips
						deletable-chips
						label="Accounts"
						:items="accounts"
						item-title="name"
						item-value="account_id"
						class="mt-5"
						spellcheck="false"
						flat
						variant="underlined"
					></v-autocomplete>
					<v-autocomplete
						v-model="dialog_domain.provider_id"
						label="Provider"
						:items="providers.filter( item => item.provider == 'hoverdotcom' || item.provider == 'spaceship' )"
						item-title="name"
						item-value="provider_id"
						class="mt-5"
						spellcheck="false"
						clearable
						flat
						variant="underlined"
					></v-autocomplete>
					</v-card-text>
					<v-card-actions class="justify-end">
						<v-btn color="primary" variant="outlined" @click="isActive.value = false; updateDomainAccount()">
							Save Domain
						</v-btn>
					</v-card-actions>
				</v-card>
				</template>
			</v-dialog>
				<v-btn 
					variant="outlined" 
					color="primary" 
					@click="activateDNSZone(dialog_domain.domain)" 
					:loading="dialog_domain.deleting_dns_zone"
					v-if="!dialog_domain.domain.remote_id">
					Activate DNS Zone
				</v-btn>
				<v-btn 
					variant="outlined" 
					color="error" 
					@click="dialog_domain.confirm_delete_dns_zone = true" 
					:loading="dialog_domain.deleting_dns_zone"
					v-if="dialog_domain.domain.remote_id">
					Delete DNS Zone
				</v-btn>
				<v-btn
					variant="outlined"
					color="error"
					@click="confirmDeleteMailgunZone(dialog_domain.domain)"
					:loading="mailgun.loadingActivate"
					v-if="dialog_domain.details.mailgun_id"
				>
					Delete Mailgun Zone
				</v-btn>
				<v-dialog v-model="dialog_domain.confirm_delete_dns_zone" max-width="500px" persistent>
					<v-card>
						<v-toolbar color="error" flat>
							<v-toolbar-title>Confirm DNS Zone Deletion</v-toolbar-title>
						</v-toolbar>
						<v-card-text class="text-body-1">
							Are you sure you want to delete the DNS zone for <strong>{{ dialog_domain.domain.name }}</strong>?
							<br><br>
							This will remove all associated DNS records from the DNS provider (Constellix). This action cannot be undone.
						</v-card-text>
						<v-card-actions>
							<v-spacer></v-spacer>
							<v-btn variant="text" @click="dialog_domain.confirm_delete_dns_zone = false">Cancel</v-btn>
							<v-btn color="error" @click="deleteDNSZone(dialog_domain.domain)">Confirm Deletion</v-btn>
						</v-card-actions>
					</v-card>
				</v-dialog>
				<v-btn variant="outlined" color="error" @click="deleteDomain()">Delete Domain</v-btn>
				</template>
				</v-card>
			</v-container>
			<v-container v-if="route == 'accounts' && ! loading_page && dialog_account.step == 2" class="mt-5">
				<v-card color="transparent" density="compact" flat subtitle="Account Options">
					<template v-slot:actions>
						<v-btn size="small" variant="outlined" @click="dialog_configure_defaults.show = true" prepend-icon="mdi-clipboard-check-outline">
							Configure Defaults
						</v-btn>
					</template>
				</v-card>
			</v-container>
			<v-container v-if="route == 'accounts' && role == 'administrator' && ! loading_page && dialog_account.step == 2">
				<v-card color="transparent" density="compact" flat subtitle="Administrator Options">
					<template v-slot:actions>
						<v-btn size="small" variant="outlined" @click="accountBulkTools()" prepend-icon="mdi-filter-variant">
							Bulk Tools on Sites
						</v-btn>
						<v-btn size="small" variant="outlined" @click="editAccountPortal()" prepend-icon="mdi-pencil">
							Edit Portal
						</v-btn>
						<v-btn size="small" variant="outlined" @click="editAccount()" prepend-icon="mdi-pencil">
							Edit Account
						</v-btn>
						<v-btn size="small" variant="outlined" color="error" @click="deleteAccount()" prepend-icon="mdi-delete">
							Delete Account
						</v-btn>
					</template>
				</v-card>
			</v-container>
			</v-container>
			<v-container v-show="loading_page">
				Loading...
			</v-container>
			<v-snackbar :timeout="3000" :multi-line="true" v-model="snackbar.show" style="z-index: 9999999;">
				{{ snackbar.message }}
				<v-btn variant="text" @click.native="snackbar.show = false">Close</v-btn>
			</v-snackbar>
		</template>
		</v-container>
		<v-dialog v-model="terminal_schedule.show" max-width="500px">
            <v-card rounded="lg">
                <v-toolbar color="primary" density="compact" flat>
                    <v-toolbar-title>Schedule Script</v-toolbar-title>
                    <v-spacer></v-spacer>
                    <v-btn icon="mdi-close" @click="terminal_schedule.show = false"></v-btn>
                </v-toolbar>
                <v-card-text class="pt-4">
                    <p class="text-body-2 mb-4">
                        Scheduling command for <strong>{{ view_console.selected_targets.length }}</strong> environment(s).
                    </p>
                    
                    <v-row>
                        <v-col cols="12" sm="6">
                            <v-menu v-model="terminal_schedule.menu_date" :close-on-content-click="false" location="bottom">
                                <template v-slot:activator="{ props }">
                                    <v-text-field
                                        v-model="terminal_schedule.date"
                                        label="Date"
                                        prepend-inner-icon="mdi-calendar"
                                        readonly
                                        v-bind="props"
                                        variant="outlined"
                                        density="compact"
                                        hide-details
                                    ></v-text-field>
                                </template>
                                <v-date-picker 
									v-model="terminal_schedule.date_obj" 
									@update:model-value="onTerminalDateChange" 
									hide-header 
									:min="new Date().toISOString().substr(0, 10)"
									color="primary"
								></v-date-picker>
                            </v-menu>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-menu v-model="terminal_schedule.menu_time" :close-on-content-click="false" location="bottom">
                                <template v-slot:activator="{ props }">
                                    <v-text-field
                                        v-model="terminal_schedule.time"
                                        label="Time"
                                        prepend-inner-icon="mdi-clock-time-four-outline"
                                        readonly
                                        v-bind="props"
                                        variant="outlined"
                                        density="compact"
                                        hide-details
                                    ></v-text-field>
                                </template>
                                <v-time-picker
                                    v-if="terminal_schedule.menu_time"
                                    v-model="terminal_schedule.time"
                                    format="24hr"
                                    @click:minute="terminal_schedule.menu_time = false"
                                    color="primary"
                                ></v-time-picker>
                            </v-menu>
                        </v-col>
                    </v-row>
                    
                    <div class="bg-grey-lighten-4 rounded pa-3 mt-4 text-caption font-monospace overflow-y-auto" style="max-height: 100px;">
                        {{ script.code }}
                    </div>

                </v-card-text>
                <v-card-actions class="pa-4">
                    <v-spacer></v-spacer>
                    <v-btn variant="text" @click="terminal_schedule.show = false">Cancel</v-btn>
                    <v-btn 
                        color="primary" 
                        variant="flat"
                        @click="confirmTerminalSchedule"
                        :loading="terminal_schedule.loading"
                        :disabled="!terminal_schedule.date || !terminal_schedule.time"
                    >
                        Schedule
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
		<!-- Terminal Window Overlay -->
		<v-slide-y-reverse-transition>
			<v-card 
				v-if="view_console.terminal_open" 
				class="terminal-window elevation-12" 
				:class="{ 'terminal-fullscreen': view_console.fullscreen }"
				theme="dark" 
				rounded="xl"
			>
				<!-- Header -->
				<div class="terminal-header d-flex align-center px-4 py-2 bg-grey-darken-4 flex-shrink-0 border-b">
					<div class="d-flex gap-2 mr-4">
						<!-- Red: Close -->
						<div class="window-dot bg-red" @click="view_console.terminal_open = false" style="cursor:pointer"></div>
						<div class="window-dot bg-yellow"></div>
						<!-- Green: Fullscreen -->
						<div class="window-dot bg-green" @click="toggleFullscreen" style="cursor:pointer" title="Toggle Fullscreen"></div>
					</div>
					
					<!-- Dynamic Header Title based on selection -->
					<span class="text-caption text-grey text-center flex-grow-1 font-monospace text-truncate px-4">
						captaincore-cli — 
						<span v-if="view_console.selected_targets.length === 0">Select target</span>
						<span v-else-if="view_console.selected_targets.length === 1">{{ view_console.selected_targets[0].home_url }}</span>
						<span v-else>{{ view_console.selected_targets.length }} environments selected</span>
					</span>

					<v-btn icon="mdi-close" variant="text" density="compact" size="small" @click="view_console.terminal_open = false"></v-btn>
				</div>
				<div class="d-flex flex-grow-1" style="overflow: hidden;">
					
					<!-- RIGHT MAIN (Output & Input - Unchanged from previous step, verify structure remains) -->
					<div class="terminal-main">
						<div class="terminal-output" ref="terminalBody">
							<div v-for="job in jobs" :key="job.job_id" class="mb-4">
								<div class="d-flex align-center text-green-accent-3 font-weight-bold mb-1 opacity-80" style="font-size: 11px;">
									<span class="mr-2">➜</span>
									<span class="text-truncate" style="max-width: 80%;">{{ job.description }}</span>
									
									<v-spacer></v-spacer>

									<v-btn
										v-if="job.status === 'running' || job.status === 'queued'" 
										size="x-small"
										color="red-accent-1"
										class="mr-4"
										@click="killCommand(job.job_id)"
										title="Stop Process"
									>
									Cancel
									</v-btn>

									<v-tooltip v-else text="Copy Output" location="bottom">
										<template v-slot:activator="{ props }">
											<v-btn 
												icon="mdi-content-copy" 
												variant="text" 
												size="small" 
												color="medium-emphasis" 
												class="mr-2"
												v-bind="props" 
												@click="copyJobStream(job)"
											></v-btn>
										</template>
									</v-tooltip>

									<span class="text-grey flex-shrink-0">{{ pretty_timestamp(job.created_at || new Date()) }}</span>
								</div>
								<div v-for="(line, i) in job.stream" :key="i" class="text-grey-lighten-1 text-break">
									<span v-if="line.trim() !== 'Finished.'">{{ line }}</span>
								</div>
								<div v-if="job.status === 'error'" class="text-error mt-1 font-weight-bold">
									<v-icon size="small" color="error">mdi-alert-circle</v-icon> Process failed.
								</div>
								<div v-if="job.status === 'running'" class="mt-1">
									<span class="cursor-block">▋</span>
								</div>
							</div>
							<div v-if="jobs.length === 0" class="d-flex align-center justify-center fill-height text-grey-darken-2">
								<div class="text-center">
									<v-icon size="64" class="mb-2">mdi-console-network</v-icon><br>
									Select environments from the sidebar<br>and enter a command.
								</div>
							</div>
						</div>

						<div class="terminal-input-area">
							<v-textarea
								v-model="script.code"
								ref="terminalInput"
								placeholder="Enter command or script..."
								variant="plain"
								rows="1"
								auto-grow
								max-rows="10"
								hide-details
								density="compact"
								class="terminal-input-field"
								spellcheck="false"
								@keydown.ctrl.enter.prevent="executeTerminalCommand"
								@keydown.meta.enter.prevent="executeTerminalCommand"
							>
								<template v-slot:prepend>
									<div class="d-flex align-center">
										<!-- Target Selector Menu -->
										<v-menu 
											v-model="view_console.target_menu" 
											:close-on-content-click="false" 
											location="top start" 
											offset="10"
											max-height="400"
										>
											<template v-slot:activator="{ props }">
												<v-btn 
													v-bind="props"
													icon
													density="compact" 
													variant="plain"
													class="mr-2"
													:color="view_console.selected_targets.length > 0 ? 'green-accent-3' : 'grey'"
													title="Select Target Environments"
													style="background:none;"
												>
													<v-icon>mdi-at</v-icon>
												</v-btn>
											</template>

											<v-card width="350" class="rounded-lg" elevation="10" theme="dark" border>
												<!-- Header -->
												<div class="pa-2 border-b bg-grey-darken-4 sticky-top">
													<v-text-field
														v-model="view_console.target_search"
														ref="targetSearchInput"
														density="compact"
														variant="outlined"
														placeholder="Search targets..."
														prepend-inner-icon="mdi-magnify"
														hide-details
														class="text-caption"
														spellcheck="false"
													></v-text-field>
													
													<div class="d-flex justify-space-between align-center mt-2 px-1">
														<span class="text-caption text-grey">
															{{ view_console.selected_targets.length }} selected
														</span>
														<div>
															<v-btn
																v-if="filteredEnvironmentsCount > 0"
																size="x-small"
																variant="text"
																color="secondary"
																class="mr-1"
																@click="selectAllMatchesToTerminal"
																title="Populate targets based on current site filters"
															>
																Add {{ filteredEnvironmentsCount }} Filtered
															</v-btn>
															<v-btn 
																size="x-small" 
																variant="text" 
																color="red-accent-2" 
																v-if="view_console.selected_targets.length > 0"
																@click="view_console.selected_targets = []"
															>
																Clear All
															</v-btn>
														</div>
													</div>
												</div>

												<!-- Scrollable List -->
												<v-list density="compact" class="bg-grey-darken-3 overflow-y-auto" style="max-height: 300px;">
													
													<!-- Render only displayed items -->
													<v-list-item 
														v-for="env in displayedConsoleTargets" 
														:key="env.environment_id"
														@click="toggleConsoleTarget(env)"
														:active="isTargetSelected(env)"
														color="green-accent-3"
													>
														<template v-slot:prepend>
															<v-checkbox-btn 
																:model-value="isTargetSelected(env)"
																density="compact"
																color="green-accent-3"
															></v-checkbox-btn>
														</template>
														
														<v-list-item-title class="font-weight-bold text-caption font-monospace">
															{{ env.home_url }}
														</v-list-item-title>
														<v-list-item-subtitle class="text-caption">
															{{ env.name }} ({{ env.environment }})
														</v-list-item-subtitle>
													</v-list-item>
													
													<!-- Infinite Scroll Sentinel -->
													<div 
														v-if="displayedConsoleTargets.length < allFilteredConsoleTargets.length" 
														v-intersect="onConsoleTargetIntersect"
														class="pa-2 text-center text-caption text-grey"
													>
														Loading more...
													</div>

													<!-- Empty State -->
													<div v-if="allFilteredConsoleTargets.length === 0" class="pa-4 text-center text-caption text-grey">
														No environments found.
													</div>
												</v-list>
											</v-card>
										</v-menu>
										<v-menu 
											v-model="view_console.recipe_menu" 
											:close-on-content-click="false" 
											location="top start" 
											offset="10"
											max-height="400"
										>
											<template v-slot:activator="{ props }">
												<v-btn 
													v-bind="props"
													icon
													density="compact" 
													variant="plain"
													class="mr-2"
													:color="view_console.recipe_menu ? 'primary' : 'grey'"
													title="Cookbook & System Tools"
													style="background:none;"
												>
													<v-icon>mdi-book-open-page-variant-outline</v-icon>
												</v-btn>
											</template>

											<v-card width="350" class="rounded-lg" elevation="10" theme="dark" border>
												<!-- Header / Search -->
												<div class="pa-2 border-b bg-grey-darken-4 sticky-top">
													<v-text-field
														v-model="view_console.recipe_menu_search"
														ref="recipeSearchInput"
														density="compact"
														variant="outlined"
														placeholder="Search..."
														prepend-inner-icon="mdi-magnify"
														hide-details
														class="text-caption"
														spellcheck="false"
													></v-text-field>
													
													<v-tabs v-model="view_console.recipe_menu_tab" density="compact" color="primary" grow class="mt-2" height="32">
														<v-tab value="system" class="text-caption">System</v-tab>
														<v-tab value="cookbook" class="text-caption">Cookbook</v-tab>
													</v-tabs>
												</div>

												<!-- Content -->
												<div style="height: 300px; overflow-y: auto;" class="bg-grey-darken-3">
													<v-window v-model="view_console.recipe_menu_tab">
														
														<v-window-item value="system">
															<v-list density="compact" class="bg-transparent recipe-menu-list">
																<template v-for="tool in filteredSystemToolsMenu" :key="tool.method">
																	<v-list-item 
																		v-if="!tool.adminOnly || role == 'administrator'"
																		:title="tool.title" 
																		@click="runSystemTool(tool.method); view_console.recipe_menu = false" 
																		link
																		class="text-caption"
																	>
																		<template v-slot:prepend>
																			<v-icon :icon="tool.icon" size="small" class="mr-2 text-primary"></v-icon>
																		</template>
																	</v-list-item>
																</template>
																<div v-if="filteredSystemToolsMenu.length === 0" class="pa-4 text-center text-caption text-grey">
																	No tools match your search.
																</div>
															</v-list>
														</v-window-item>

														<v-window-item value="cookbook">
															<v-list density="compact" class="bg-transparent recipe-menu-list">
																<v-tooltip 
																	v-for="recipe in filteredRecipeMenuRecipes" 
																	:key="recipe.recipe_id" 
																	:text="recipe.title" 
																	location="right" 
																	max-width="300"
																	:disabled="recipe.title.length <= 30"
																>
																	<template v-slot:activator="{ props }">
																		<v-list-item 
																			v-bind="props" 
																			@click="handleTerminalRecipeClick(recipe); view_console.recipe_menu = false" 
																			link
																			class="text-caption"
																		>
																			<template v-slot:prepend>
																				<v-icon 
																					:icon="recipe.public == 1 ? 'mdi-package-variant-closed' : 'mdi-script-text-outline'" 
																					size="small" 
																					class="mr-2" 
																					:class="recipe.public == 1 ? 'text-primary' : 'opacity-60'">
																				</v-icon>
																			</template>
																			
																			<v-list-item-title class="text-truncate">
																				{{ recipe.title }}
																			</v-list-item-title>

																			<template v-slot:append>
																				<v-icon v-if="recipe.public == 1" size="x-small" class="text-disabled">mdi-lock-outline</v-icon>
																			</template>
																		</v-list-item>
																	</template>
																</v-tooltip>
																<div v-if="filteredRecipeMenuRecipes.length === 0" class="pa-4 text-center text-caption text-grey">
																	No recipes match your search.
																</div>
															</v-list>
														</v-window-item>

													</v-window>
												</div>
											</v-card>
										</v-menu>

										<span class="text-green-accent-3 font-weight-bold mr-1">$</span>
									</div>
								</template>

								<template v-slot:append>
									<v-btn
										variant="plain"
										icon="mdi-content-save-outline"
										density="compact"
										size="normal"
										color="grey-lighten-1"
										class="mr-1"
										title="Save as Recipe"
										:disabled="!script.code"
										style="background: none;"
										@click="openSaveAsRecipe"
									></v-btn>
									<v-btn
										variant="plain"
										icon="mdi-clock-plus-outline"
										density="compact"
										size="normal"
										color="grey-lighten-1"
										class="mr-1"
										title="Schedule Script"
										:disabled="!script.code"
										style="background: none;"
										@click="openTerminalSchedule"
									></v-btn>
									<v-btn 
										variant="tonal" 
										density="comfortable"
										class="px-2 ml-2"
										style="min-width: auto; height: 24px;"
										@click="executeTerminalCommand" 
										:disabled="!script.code"
									>
										<span class="text-capitalize mr-3">Run</span>
										<span class="text-caption font-weight-black">{{ runShortcutLabel }}</span>
									</v-btn>
								</template>
							</v-textarea>
						</div>
					</div>
				</div>
			</v-card>
		</v-slide-y-reverse-transition>
		<div class="activity-island-container">
			<v-fade-transition>
				<div v-if="(runningJobs > 0 || view_console.show) && !view_console.fullscreen" class="d-flex flex-column align-center">
					<v-card elevation="10" rounded="pill" color="surface" class="activity-island pr-1" border @click="view_console.terminal_open = !view_console.terminal_open">
						<div class="d-flex align-center pl-4 pr-2 py-2" style="cursor: pointer; min-width: 300px; max-width: 600px;">
							<!-- Spinner/Status Icon -->
							<div class="mr-3 d-flex align-center">
								<v-progress-circular
									v-if="runningJobs > 0"
									indeterminate
									color="primary"
									size="20"
									width="2"
								></v-progress-circular>
								<v-icon v-else color="success" size="20">mdi-check-circle</v-icon>
							</div>

							<!-- Live Stream Text -->
							<div class="d-flex flex-column flex-grow-1 overflow-hidden mr-3">
								<span class="text-caption font-weight-bold text-truncate">
									{{ activeJobDescription || 'Console Ready' }}
								</span>
								<span class="text-caption text-medium-emphasis font-monospace text-truncate">
									{{ activeJobLastLine || 'Waiting for output...' }}
								</span>
							</div>

							<!-- Toggle Chevron -->
							<v-btn
								icon
								variant="text"
								size="small"
								density="comfortable"
								:style="{ transform: view_console.terminal_open ? 'rotate(180deg)' : 'rotate(0deg)' }"
							>
								<v-icon>mdi-chevron-up</v-icon>
							</v-btn>
						</div>
					</v-card>
					<!-- Close/Hide Button -->
					<v-btn 
						icon="mdi-chevron-down" 
						variant="text" 
						size="x-small" 
						density="compact"
						color="medium-emphasis" 
						class="mt-1" 
						@click="view_console.show = false; view_console.terminal_open = false"
						title="Hide Console"
					></v-btn>
				</div>
			</v-fade-transition>
		</div>
		<v-snackbar :timeout="3000" :multi-line="true" v-model="snackbar.show" style="z-index: 9999999;">
			{{ snackbar.message }}
			<v-btn variant="text" @click.native="snackbar.show = false">Close</v-btn>
		</v-snackbar>
		</v-main>
	</v-app>
</div>
<?php if ( substr( $_SERVER['SERVER_NAME'], -10) == '.localhost' ) { ?>
<script src="<?php echo $plugin_url; ?>public/js/vue.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/qs.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/axios.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/vuetify.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/vue-upload-component.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/numeral.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/frappe-charts.min.js"></script>
<?php } else { ?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.20/dist/vue.global.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qs@6.9.1/dist/qs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@0.19.0/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@3.9.6/dist/vuetify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue-upload-component@3.1.17/dist/vue-upload-component.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/numeral@2.0.6/numeral.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/frappe-charts@1.6.1/dist/frappe-charts.min.umd.js"></script>
<?php } ?>
<script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.13/dayjs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/components/prism-log.min.js"></script>
<script src="https://js.stripe.com/v3/"></script>
<script src="<?php echo $plugin_url; ?>public/js/kjua.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/moment.min.js"></script>
<script src="<?php echo $plugin_url; ?>public/js/core.js"></script>
<script>
<?php if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { ?>
wc_countries = <?php $countries = ( new WC_Countries )->get_allowed_countries(); foreach ( $countries as $key => $county ) { $results[] = [ "title" => $county, "value" => $key ]; }; echo json_encode( $results ); ?>;
wc_states = <?php echo json_encode( array_merge( WC()->countries->get_allowed_country_states(), WC()->countries->get_shipping_country_states() ) ); ?>;
wc_address_i18n_params = <?php echo json_encode( WC()->countries->get_country_locale() ); ?>;
stripe = Stripe('<?php echo ( new WC_Gateway_Stripe )->publishable_key; ?>');
<?php } else { ?>
wc_countries = []
wc_states = []
stripe = ""
<?php } ?>
(function(){var w=window;var ic=w.Intercom;if(typeof ic==="function"){ic('reattach_activator');ic('update',w.intercomSettings);}else{var d=document;var i=function(){i.c(arguments);};i.q=[];i.c=function(args){i.q.push(args);};w.Intercom=i;var l=function(){var s=d.createElement('script');s.type='text/javascript';s.async=true;s.src='https://widget.intercom.io/widget/<?= CaptainCore\Configurations::get()->intercom_embed_id; ?>';var x=d.getElementsByTagName('script')[0];x.parentNode.insertBefore(s,x);};if(document.readyState==='complete'){l();}else if(w.attachEvent){w.attachEvent('onload',l);}else{w.addEventListener('load',l,false);}}})();			
ajaxurl = "/wp-admin/admin-ajax.php"

const captainCoreColors = <?php echo json_encode( CaptainCore\Configurations::colors() ); ?>;

const { createApp, ref, computed, reactive } = Vue;
const { createVuetify, useDisplay, useGoTo } = Vuetify;

const vuetify = createVuetify({
	components: {
		...Vuetify.components,      // Spread all standard Vuetify components
	},
	theme: {
		defaultTheme: 'light',
		themes: {
			light: {
				dark: false,
				colors: captainCoreColors
			},
			dark: {
				dark: true,
				colors: {
					primary: '#757575',
					secondary: '#424242',
					accent: '#313131',
					error: '#FF5252',
					info: '#2196F3',
					success: '#4CAF50',
					warning: '#FFC107',
					surface: '#212121',
					background: '#121212',
				}
			}
		}
	}
});

const app = createApp({
	setup() {
        // Call useDisplay to get reactive display properties
        const { smAndDown, mdAndUp, lgOnly, name } = useDisplay();
		const currentThemeColors = reactive(captainCoreColors);
		const goTo = useGoTo()

        return {
            isMobile: smAndDown, // Expose smAndDown to the template as 'isMobile'
            currentBreakpoint: name, // Example: expose the current breakpoint name
			currentThemeColors,
			goTo
        };
    },
    data() {
	  return {
		theme: 'light',
		colors: { 
			primary: false,
			secondary: false,
			accent: false,
			error: false,
			info: false,
			success: false,
			warning: false,
		},
		files: {
			html: 'mdi-language-html5',
			js: 'mdi-nodejs',
			json: 'mdi-code-json',
			md: 'mdi-language-markdown',
			pdf: 'mdi-file-pdf',
			png: 'mdi-file-image',
			txt: 'mdi-file-document-outline',
			xls: 'mdi-file-excel',
			jpg: 'mdi-file-image',
			gif: 'mdi-file-image',
			php: 'mdi-file-code',
		},
		archives: [],
		archives_loading: false,
		archive_search: "",
		dialog_archive_link: { show: false, url: "", loading: false },
		configurations: <?php echo json_encode( ( new CaptainCore\Configurations )->get() ); ?>,
		configurations_step: 0,
		configurations_loading: true,
		notifications: false,
		themeFilterMenu: false,
		pluginFilterMenu: false,
		coreFilterMenu: false,
		isUnassignedFilterActive: false,
		isOutstandingFilterActive: false,
		isEmptyFilterActive: false,
		provider_actions: [],
		hosting_intervals: [{ title: 'Yearly', value: '12' },{ title: 'Monthly', value: '1' },{ title: 'Quarterly', value: '3' },{ title: 'Biannual', value: '6' }],
		footer_height: "28px",
		login: { user_login: "", user_password: "", errors: "", info: "", loading: false, lost_password: false, message: "", tfa_code: "" },
		wp_nonce: "",
		wp_nonce_retry: false,
		footer: <?php echo captaincore_footer_content_extracted(); ?>,
		drawer: null,
		billing_loading: true,
		billing_tabs: 1,
		home_link: "<?php echo home_url(); ?>",
		remote_upload_uri: "<?php echo get_option( 'options_remote_upload_uri' ); ?>",
		loading_page: true,
		expanded: [],
		accounts: [],
		accountportals: [],
		account_tab: null,
		modules: { billing: <?php if ( defined( "CAPTAINCORE_CUSTOM_DOMAIN" ) ) { echo "false"; } else { echo "true"; } ?>, dns: <?php if ( defined( "CONSTELLIX_API_KEY" ) and defined( "CONSTELLIX_SECRET_KEY" ) ) { echo "true"; } else { echo "false"; } ?> },
		dialog_bulk: { show: false, tabs_management: "tab-Sites", environment_selected: "Production" },
		dialog_bulk_tools: { show: false, environment_selected: "Production" },
		dialog_job: { show: false, task: {} },
		dialog_breakdown: false,
		dialog_captures: { site: {}, auth: { username: "", password: ""}, pages: [{ page: ""}], capture: { pages: [] }, image_path:"", selected_page: "", captures: [], mode: "screenshot", loading: true, image_loading: false, show: false, show_configure: false },
		dialog_delete_user: { show: false, site: {}, users: [], username: "", reassign: null },
		dialog_apply_https_urls: { show: false, site_id: "", site_name: "", sites: [] },
		dialog_copy_site: { show: false, site: {}, options: [], destination: null },
		dialog_edit_site: { show: false, show_vars: false, loading: false, site: {
				key: "",
				environments: [
					{"environment": "Production", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"","monitor_enabled":"1","updates_enabled":"1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" },
					{"environment": "Staging", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"","monitor_enabled":"0","updates_enabled":"1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" }
				],
			},
		},
		dialog_new_domain: { show: false, domain: { name: "", account_id: "", site_id: "", create_dns_zone: true }, loading: false, errors: [] },
		dialog_new_provider: { show: false, provider: { name: "", provider: "", credentials: [ { "name": "", "value": "" } ] }, loading: false, errors: [] },
		dialog_edit_provider: { show: false, provider: { name: "", provider: "", credentials: [ { "name": "", "value": "" } ] }, loading: false, errors: [] },
		dialog_configure_defaults: { show: false, loading: false },
		dialog_domain: { show: false, account: {}, accounts: [], updating_contacts: false, updating_nameservers: false, ignore_warnings: false, auth_code: "", fetch_auth_code: false, update_privacy: false, update_lock: false, provider_id: "", provider: { contacts: {} }, contact_tabs: "", tabs: "dns", show_import: false, import_json: "", domain: {}, records: [], nameservers: [], results: [], errors: [], info: [], loading: true, saving: false, step: 1, details: {}, activating_forwarding: false, confirm_mx_overwrite: false, forwards_domain: { loading: false, data: null }, forwards: { loading: false, items: [], show_dialog: false, edited_item: { name: '', recipients: '', is_enabled: true }, edited_index: -1 }, update_account: { show: false, site: null } },
		dialog_backup_snapshot: { show: false, site: {}, email: "<?php echo $user->email; ?>", current_user_email: "<?php echo $user->email; ?>", filter_toggle: true, filter_options: [] },
		dialog_backup_configurations: { show: false, settings: { mode: "", interval: "", active: true } },
		dialog_file_diff: { show: false, response: "", loading: false, file_name: "" },
		dialog_launch: { show: false, site: {}, domain: "" },
		dialog_share: {
			show: false,
			loading: false,
			sending: false,
			site_id: null,
			email: "",
			error: "",
			preview: {
				site_name: "",
				account_name: "",
				total_sites: 0,
				total_domains: 0,
				has_account_access: false,
				sites_list: []
			}
		},
		dialog_toggle: { show: false, site_name: "", site_id: "", business_name: "", business_link: "" },
		dialog_mailgun: { show: false, site: {}, response: { items: [], paging: {} }, loading: false, loadingMore: false, domain_id: null },
		dialog_mailgun_details: { show: false, event: {} },
		dialog_migration: { show: false, sites: [], site_name: "", site_id: "", update_urls: true, backup_url: "" },
		dialog_modify_plan: { show: false, site: {}, date_selector: false, hosting_plans: [], selected_plan: "", plan: { limits: {}, addons: [], charges: [], credits: [], next_renewal: "" }, customer_name: "", interval: "12" },
		dialog_customer_modify_plan: { show: false, hosting_plans: [], selected_plan: "", subscription: {  plan: { limits: {}, addons: [], next_renewal: "" } } },
		dialog_theme_and_plugin_checks: { show: false, site: {}, loading: false },
		dialog_update_settings: { show: false, environment: { updates_enabled: 1 }, themes: [], plugins: [], loading: false },
		dialog_fathom: { show: false, site: {}, environment: {}, loading: false, editItem: false, editedItem: {}, editedIndex: -1 },
		dialog_mailgun_config: { show: false, loading: false },
		dialog_account: { show: false, loading: false, records: { account: { defaults: { recipes: [] } } }, new_invite: false, new_invite_email: "", step: 1 },
		dialog_invoice: { show: false, loading: false, paying: false, customer: false, response: "", payment_method: "", card: {}, error: "" },
		dialog_domain_mappings: { show: false, loading: true, domains: [], new_domain: "", errors: [], site_id: null, env_name: "" },
		verificationDialog: { show: false, domain: {}, loading: false },
		dialog_new_account: { show: false, name: "", records: {} },
		dialog_user: { show: false, user: {}, errors: [] },
		dialog_new_user: { first_name: "", last_name: "", email: "", login: "", account_ids: [], errors: [] },
		dialog_new_site_kinsta: { show: false, errors: [], working: false, verifing: true, connection_verified: false, kinsta_token: "", site: { name: "", provider_id: "1", clone_site_id: "", domain: "", datacenter: "us-ashburn-1", shared_with: [], account_id: "", customer_id: "" } },
		dialog_new_site_rocketdotnet: { show: false, site: { name: "", domain: "", datacenter: "", shared_with: [], account_id: "", customer_id: "" } },
		dialog_request_site: { show: false, request: { name: "", account_id: "", notes: "" } },
		provider_options: [
			{
				"title": "Analytics - Fathom",
				"value": "fathom"
			},
			{
				"title": "DNS - Constellix",
				"value": "constellix"
			},
			{
				"title": "Domain - Hover.com",
				"value": "hoverdotcom"
			},
			{
				"title": "Domain - Spaceship",
				"value": "spaceship"
			},
			{
				"title": "Email - Forward Email",
				"value": "forwardemail"
			},
			{
				"title": "Email - Mailgun",
				"value": "mailgun"
			},
			{
				"title": "Hosting - Kinsta",
				"value": "kinsta",
				"fields": [ { name: "Token", value: "token" } ]
			},
			{
				"title": "Hosting - GridPane",
				"value": "gridpane"
			},
			{
				"title": "Hosting - Rocket.net",
				"value": "rocketdotnet"
			},
			{
				"title": "Hosting - WP Engine",
				"value": "wpengine",
				"fields": [ "authorization_basic" ]
			},
			{
				"title": "Live chat - Intercom",
				"value": "intercom",
				"fields": [ "embed_id", "secret_key" ]
			},
			{
				"title": "Marketplace - Envato",
				"value": "envato",
				"fields": [ "token" ]
			},
			{
				"title": "Plugin - Gravity SMTP",
				"value": "gravitysmtp",
				"fields": [ "license", "download_url" ]
			},
		],
		datacenters: [
			{
				"title": "Taiwan (TW) 🚀",
				"value": "asia-east1"
			},
			{
				"title": "Hong Kong (HK)",
				"value": "asia-east2"
			},
			{
				"title": "Tokyo (JP)",
				"value": "asia-northeast1"
			},
			{
				"title": "Osaka (JP)",
				"value": "asia-northeast2"
			},
			{
				"title": "Seoul (KR)",
				"value": "asia-northeast3"
			},
			{
				"title": "Mumbai (IN)",
				"value": "asia-south1"
			},
			{
				"title": "Delhi (IN) 🚀",
				"value": "asia-south2"
			},
			{
				"title": "Singapore (SG) 🚀",
				"value": "asia-southeast1"
			},
			{
				"title": "Jakarta (ID)",
				"value": "asia-southeast2"
			},
			{
				"title": "Sydney (AU) 🚀",
				"value": "australia-southeast1"
			},
			{
				"title": "Melbourne (AU)",
				"value": "australia-southeast2"
			},
			{
				"title": "Warsaw (PL)",
				"value": "europe-central2"
			},
			{
				"title": "Finland (FI)",
				"value": "europe-north1"
			},
			{
				"title": "Madrid (ES)",
				"value": "europe-southwest1"
			},
			{
				"title": "Belgium (BE) 🚀",
				"value": "europe-west1"
			},
			{
				"title": "London (UK)",
				"value": "europe-west2"
			},
			{
				"title": "Frankfurt (DE) 🚀",
				"value": "europe-west3"
			},
			{
				"title": "Eemshaven (NL) 🚀",
				"value": "europe-west4"
			},
			{
				"title": "Zürich (CH)",
				"value": "europe-west6"
			},
			{
				"title": "Milan (IT)",
				"value": "europe-west8"
			},
			{
				"title": "Paris (FR)",
				"value": "europe-west9"
			},
			{
				"title": "Tel Aviv (IS)",
				"value": "me-west1"
			},
			{
				"title": "Montreal (CA)",
				"value": "northamerica-northeast1"
			},
			{
				"title": "Toronto (CA)",
				"value": "northamerica-northeast2"
			},
			{
				"title": "São Paulo (BR)",
				"value": "southamerica-east1"
			},
			{
				"title": "Santiago (CL)",
				"value": "southamerica-west1"
			},
			{
				"title": "Iowa (US Central) 🚀",
				"value": "us-central1"
			},
			{
				"title": "South Carolina (US East 1) 🚀",
				"value": "us-east1"
			},
			{
				"title": "Ashburn (US East) 🚀",
				"value": "us-ashburn-1"
			},
			{
				"title": "Chicago (US Central) 🚀",
				"value": "us-chicago-1"
			},
			{
				"title": "Columbus (US East 5)",
				"value": "us-east5"
			},
			{
				"title": "Dallas US (us-south1)",
				"value": "us-south1"
			},
			{
				"title": "Oregon (US West)",
				"value": "us-west1"
			},
			{
				"title": "Los Angeles (US West 2)",
				"value": "us-west2"
			},
			{
				"title": "Salt Lake City (US West 3)",
				"value": "us-west3"
			},
			{
				"title": "Las Vegas (US West 4) 🚀",
				"value": "us-west4"
			}
		],
		kinsta_providers: <?php echo json_encode( CaptainCore\Providers\Kinsta::list() ); ?>,
		kinsta_provider_sites: [<?php echo json_encode( CaptainCore\Providers\Kinsta::list_sites() ); ?>],
		clone_sites: [],
		requested_sites: <?php echo json_encode( ( new CaptainCore\User )->fetch_requested_sites() ); ?>,
		new_invite: { account: {}, records: {} },
		new_account: { password: "" },
		timeline_logs: [],
		route_path: "",
		route: "",
		routes: {
			'/': '',
			'/accounts': 'accounts',
			'/archives': 'archives',
			'/billing': 'billing',
			'/cookbook': 'cookbook',
			'/configurations': 'configurations',
			'/connect': 'connect',
			'/defaults': 'defaults',
			'/domains': 'domains',
			'/handbook': 'handbook',
			'/health': 'health',
			'/keys': 'keys',
			'/login': 'login',
			'/profile' : 'profile',
			'/sites': 'sites',
			'/subscriptions': 'subscriptions',
			'/users': 'users',
		},
		selected_nav: "",
		querystring: window.location.search,
		page: 1,
		socket: "<?php echo captaincore_fetch_socket_address() . "/ws"; ?>",
		timezones: <?php echo json_encode( timezone_identifiers_list() ); ?>,
		jobs: [],
		keys: [],
		defaults: [],
		script: { code: "", menu: false, menu_time: false, menu_date: false, time: "", date: "", timezone: "" },
		recipes: [],
		processes: [],
		billing: { valid: true, rules: {}, payment_methods: [], address: { last_name: "", email: "", city: "", line1: "", line2: "", postal_code: "", state: "" } },
		subscriptions: [],
		new_payment: { card: {}, show: false, error: "" },
		current_user_email: "<?php echo $user->email; ?>",
		current_user_login: "<?php echo $user->login; ?>",
		current_user_registered: "<?php echo $user->registered; ?>",
		current_user_hash: "<?php echo $user->hash; ?>",
		current_user_display_name: "<?php echo $user->display_name; ?>",
		profile: { first_name: "<?php echo $user->first_name; ?>", last_name: "<?php echo $user->last_name; ?>", email: "<?php echo $user->email; ?>", login: "<?php echo $user->login; ?>", display_name: "<?php echo $user->display_name; ?>", new_password: "", errors: [], tfa_activate: false, tfa_enabled: <?php echo $user->tfa_enabled; ?>, tfa_uri: "", tfa_token: "" },
		stats: { from_at: "<?php echo date("Y-m-d", strtotime( date("Y-m-d" ). " -12 months" ) ); ?>", to_at: "<?php echo date("Y-m-d" ); ?>", from_at_select: false, to_at_select: false, grouping: "Month" },
		role: "<?php echo $user->role; ?>",
		dialog_processes: { show: false, processes: [], conn: {}, stream: [], loading: true },
		dialog_push_to_other: {
			show: false,
			loading: false,
			search: '',
			targets: [],
			source_site: null,
			source_env: null,
			target_site: null,
			target_env: null,
			confirm: {
				show: false,
			},
			currentPage: 1,
			itemsPerPage: 100,
		},
		dialog_new_log_entry: { show: false, sites: [], site_name: "", process: "", description: "" },
		dialog_edit_log_entry: { show: false, site_name: "", log: {} },
		dialog_edit_script: { show: false, script: { script_id: "", code: "", run_at_time: "", run_at_date: "" } },
		dialog_log_history: { show: false, loading: true, logs: [], pagination: {} },
		dialog_handbook: { show: false, process: {} },
		dialog_key: { show: false, key: {} },
		new_process: { show: false, name: "", time_estimate: "", repeat_interval: "as-needed", repeat_quantity: "", roles: "", description: "" },
		dialog_edit_process: { show: false, process: {} },
		process_roles: <?php echo ( ! empty( get_option('captaincore_process_roles') ) ? get_option('captaincore_process_roles') : "[]" ); ?>,
		shared_with: [],
		new_key: { show: false, title: "", key: "" },
		new_key_user: { show: false, title: "", key: "" },
		dialog_new_site: {
			provider: "kinsta",
			show: false,
			show_vars: false,
			environment_vars: [],
			saving: false,
			key: null,
			site: "",
			name: "",
			errors: [],
			shared_with: [],
			account_id: "",
			customer_id: "",
			environments: [
				{"environment": "Production", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"",monitor_enabled: "1",updates_enabled: "1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" },
				{"environment": "Staging", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"",monitor_enabled: "0",updates_enabled: "1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" }
			],
		},
		header_timeline: [
			{"title":"","value":"name","sortable":false,"width":"56"},
			{"title":"Description","value":"name","sortable":false},
			{"title":"Person","value":"done-by","sortable":false,"width":"180"},
			{"title":"Date","value":"date","sortable":false,"width":"220"},
			{"title":"","value":"","sortable":false,"width":"58"},
		],
		domains: [],
		domains_loading: true,
		sites_loading: true,
		domain_search: "",
		quicksave_search: "",
		quicksave_search_results: { loading: false, search: "", search_type: "", search_field: "", items: [] },
		quicksave_search_type: "plugin",
		quicksave_search_field: "name",
		account_search: "",
		subscription_search: "",
		revenue_estimated: [],
		mailgun: {
            subdomainDialog: false,
            deployDialog: false,
            subdomain: 'mg',
            deployName: '',
			deployFormValid: true,
            activeDomain: null,
            activeSite: null,
            validSubdomain: false,
            loadingVerify: false,
			loadingActivate: false,
			loadingDeploy: false,
            subdomainRules: [
                v => !!v || 'Subdomain is required',
            ],
            data: null,
            loading: false,
        },
		dialog_mailgun_deploy: {
            show: false,
            search: '',
            currentPage: 1,
            itemsPerPage: 10,
        },
		new_recipe: { show: false, title: "", content: "", public: 1 },
		backup_set_files: [],
		dialog_cookbook: { show: false, recipe: {}, content: "" },
		dialog_billing: { step: 1 },
		dialog_site: { loading: true, step: 1, backup_step: 1, grant_access: [], grant_access_menu: false, desired_environment_id: null, environment_selected: { environment_id: "0", expanded_backups: [], quicksave_panel: [], plugins:[], themes: [], core: "", screenshots: [], users_selected: [], users: "Loading", address: "", capture_pages: [], environment: "Production", environment_label: "Production Environment", stats: "Loading", plugins_selected: [], themes_selected: [], loading_plugins: false, loading_themes: false }, site: { name: "", site: "", screenshots: {}, timeline: [], environments: [], users: [], timeline: [], update_log: [], key: null, tabs: "tab-Site-Management", tabs_management: "tab-Info", account: { plan: "Loading" }  } },
		dialog_site_request: { show: false, request: {} },
		dialog_edit_account: { show: false, account: {} },
		dialog_account_portal: { show: false, portal: { domain: "", configuration: {}, email: { host: "", port: "", encryption_type: "tls", username: "", password: "" }, colors: { primary: "#0D47A1", secondary: "#424242", accent: "#82B1FF", error: "#FF5252", info: "#0D47A1", success: "#4CAF50", warning: "#FFC107" } }, colors: { primary: false, secondary: false, accent: false, error: false, info: false, success: false, warning: false } },
		roles: [{ name: "Subscriber", value: "subscriber" },{ name: "Contributor", value: "contributor" },{ name: "Author", value: "author" },{ name: "Editor", value: "editor" },{ name: "Administrator", value: "administrator" }],
		new_plugin: { show: false, sites: [], site_name: "", environment_selected: "", loading: false, tabs: null, page: 1, search: "", api: {}, envato: { items: [], search: "" } },
		new_theme: { show: false, sites: [], site_name: "", environment_selected: "", loading: false, tabs: null, page: 1, search: "", api: {}, envato: { items: [], search: "" } },
		bulk_edit: { show: false, site_id: null, type: null, items: [] },
		upload: [],
		selected_site: {},
		active_console: 0,
		view_console: { show: false, terminal_open: false, fullscreen: false, selected_targets: [], search: '', target_search: '', target_menu: false, target_limit: 100, recipe_menu: false, recipe_menu_search: '', recipe_menu_tab: 'system' },
			terminal_schedule: {
			show: false,
			date: "",
			date_obj: null,
			time: "",
			menu_date: false,
			menu_time: false,
			loading: false
		},
		system_tools: [
			{ title: 'Apply HTTPS Urls', icon: 'mdi-rocket-launch', method: 'viewApplyHttpsUrlsBulk' },
			{ title: 'Deploy Defaults', icon: 'mdi-refresh', method: 'siteDeployBulk' },
			{ title: 'Toggle Site Status', icon: 'mdi-toggle-switch', method: 'toggleSiteBulk' },
			{ title: 'Manual Sync Details', icon: 'mdi-sync', method: 'bulkSyncSites' },
			{ title: 'Add Plugin', icon: 'mdi-plus-box', method: 'addPluginBulk' },
			{ title: 'Add Theme', icon: 'mdi-plus', method: 'addThemeBulk' },
			{ title: 'New Log Entry', icon: 'mdi-checkbox-marked', method: 'showLogEntryBulk', adminOnly: true },
			{ title: 'Launch Site', icon: 'mdi-earth', method: 'launchSiteDialog' },
			{ title: 'Open in Browser', icon: 'mdi-open-in-new', method: 'bulkactionLaunch' },
		],
		search: null,
		users_search: "",
		sites_selected: [],
		filter_logic: "and",
		filter_version_logic: "and",
		filter_status_logic: "and",
		site_filters: <?php echo json_encode( ( new CaptainCore\Environments )->filters() ); ?>,
		site_filters_core: <?php echo json_encode( ( new CaptainCore\Environments )->filters_for_core() ); ?>,
		site_filter_version: null,
		site_filter_status: null,
		toggle_site: 'cards',
		toggle_plan: true,
		countries: wc_countries,
		states: wc_states,
		states_selected: [],
		environments: [],
		filtered_environment_ids: [],
		sites: [],
		providers: [],
		users: [],
		user_search: "",
		header_themes: [
			{ title: 'Name', value: 'title' },
			{ title: 'Slug', value: 'name' },
			{ title: 'Version', value: 'version' },
			{ title: 'Status', value: 'status', width: "100px" },
			{ title: 'Actions', value: 'actions', width: "90px", sortable: false }
		],
		header_plugins: [
			{ title: 'Name', value: 'title' },
			{ title: 'Slug', value: 'name' },
			{ title: 'Version', value: 'version' },
			{ title: 'Status', value: 'status', width: "100px" },
			{ title: 'Actions', value: 'actions', width: "90px", sortable: false }
		],
		header_users: [
			{ title: 'Login', key: 'user_login' },
			{ title: 'Display Name', key: 'display_name' },
			{ title: 'Email', key: 'user_email' },
			{ title: 'Role(s)', key: 'roles' },
			{ title: '', key: 'actions', sortable: false, align: 'end' }
		],
		applied_theme_filters: [],
		applied_plugin_filters: [],
		applied_core_filters: [],
		select_bulk_action: null,
		bulk_actions: [
			{ header: "Script" },
			{ name: "Migrate", value: "migrate", arguments: [
				{ name: "Url", value: "url" },
				{ name: "Skip url override", value: "skip-url-override" }
			]},
			{ name: "Apply SSL", value: "applyssl"  },
			{ name: "Apply SSL with www", value: "applysslwithwww" },
			{ name: "Launch", value: "launch" },
			{ header: "Command" },
			{ name: "Backup", value: "backup" },
			{ name: "SSH", value: "ssh", arguments: [
				{ name: "Commands", value: "command" },
				{ name: "Script", value: "script" }
			]},
			{ name: "Sync", value: "sync" },
			{ name: "Activate", value: "activate" },
			{ name: "Deactivate", value: "deactivate" },
			{ name: "Snapshot", value: "snapshot" },
			{ name: "Remove", value: "remove" }
		 ],
		 select_bulk_action_arguments: null,
		 snackbar: { show: false, message: "" }
	  }
	},
	watch: {
		route() {
			this.triggerRoute()
		},
		route_path() {
			this.triggerPath()
		},
		runningJobs() {
			this.view_console.show = true
		},
		'view_console.fullscreen'(val) {
			localStorage.setItem('captaincore-terminal-fullscreen', val);
		},
		'view_console.recipe_menu'(isOpen) {
			if (isOpen) {
				this.view_console.recipe_menu_search = '';
				setTimeout(() => {
					if (this.$refs.recipeSearchInput) {
						this.$refs.recipeSearchInput.focus();
					}
				}, 100);
			}
		},
		'dialog_site.grant_access_menu'(val) {
            if (val) {
                setTimeout(() => {
					if (this.$refs.accountAutocomplete) {
						this.$refs.accountAutocomplete.focus();
					}
				}, 100);
            }
        },
		combinedAppliedFilters(newFilters, oldFilters) {
			// Only proceed if the primary filters have actually changed.
			if (JSON.stringify(newFilters) === JSON.stringify(oldFilters)) {
				return;
			}

			// If all primary filters are now gone, reset secondary options and update the site list.
			if (!newFilters || newFilters.length === 0) {
				this.site_filter_version = null;
				this.site_filter_status = null;
				this.filterSites(); // This will reset the site list via the API
				return;
			}

			const filterNames = newFilters.map(f => f.name).join(',');

			axios.get(`/wp-json/captaincore/v1/filters/${filterNames}/versions/`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.site_filter_version = response.data;
			})
			.catch(error => {
				console.error("Error fetching filter versions:", error);
				this.site_filter_version = null;
			});

			axios.get(`/wp-json/captaincore/v1/filters/${filterNames}/statuses/`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.site_filter_status = response.data;
			})
			.catch(error => {
				console.error("Error fetching filter statuses:", error);
				this.site_filter_status = null;
			});
		},
		filter_logic() {
			this.filterSites();
		},
		'filter_version_logic': function() {
			this.filterSites();
		},
		'filter_status_logic': function() {
			this.filterSites();
		},
		'new_payment.show'(isOpening) {
			if (isOpening) {
				this.$nextTick(() => {
					this.prepNewPayment();
				});
			}
		},
		'view_console.terminal_open'(val) {
			if (val) {
				// Scroll to bottom immediately upon opening
				this.scrollToTerminalBottom();
				
				// Focus the input field for immediate typing
				setTimeout(() => {
					if (this.$refs.terminalInput) {
						this.$refs.terminalInput.focus();
					}
				}, 100);
			}
		},
		'view_console.target_search'() {
			this.view_console.target_limit = 100;
			// Scroll list to top if possible (optional)
		},
		'view_console.target_menu'(isOpen) {
			if (isOpen) {
				this.view_console.target_limit = 100;
				this.view_console.target_search = ''; // Optional: clear search on open
				
				// Focus the search box on open
				setTimeout(() => {
					if (this.$refs.targetSearchInput) {
						this.$refs.targetSearchInput.focus();
					}
				}, 100);
			}
		},
		'dialog_site.environment_selected.expanded_backups': function(newlyExpandedIds, previouslyExpandedIds) {
			const siteId = this.dialog_site.site.site_id;

			const currentExpandedArray = Array.isArray(newlyExpandedIds) ? newlyExpandedIds : [];
			const previousExpandedArray = Array.isArray(previouslyExpandedIds) ? previouslyExpandedIds : [];

			if (currentExpandedArray.length > 1) {
				let intendedTargetId = null;

				const previousIdsSet = new Set(previousExpandedArray);
				const addedIds = currentExpandedArray.filter(id => !previousIdsSet.has(id));

				if (addedIds.length === 1) {
					intendedTargetId = addedIds[0];
				} else if (currentExpandedArray.length > 0) {
					intendedTargetId = currentExpandedArray[currentExpandedArray.length - 1];
				}

				if (intendedTargetId) {
					this.dialog_site.environment_selected.expanded_backups = [intendedTargetId];
					return;
				} else {
					if (this.dialog_site.environment_selected.expanded_backups.length > 0) {
						this.dialog_site.environment_selected.expanded_backups = [];
					}
					return;
				}
			}

			const finalExpandedId = (currentExpandedArray.length === 1) ? currentExpandedArray[0] : null;
			const previousSingleId = (previousExpandedArray.length === 1) ? previousExpandedArray[0] : null;

			if (finalExpandedId) {
				const needsAction = (finalExpandedId !== previousSingleId) ||
									(previousExpandedArray.length > 1 && currentExpandedArray.length === 1);

				if (needsAction) {
					const itemToExpand = this.dialog_site.environment_selected.backups.find(b => b.id === finalExpandedId);
					if (itemToExpand) {
						this.getBackup(itemToExpand.id, siteId);
					}
				}
			} else {
				if (previousExpandedArray.length > 0) {
					// This block is where you would put any logic that needs to run
					// when an item is collapsed (e.g., clearing details).
					// If there's no specific action beyond what was previously logged,
					// this can remain as is or be used for future collapse handling.
				}
			}
		}
    },
	mounted() {
		const savedTheme = localStorage.getItem('captaincore-theme');
		if (savedTheme) {
			this.theme = savedTheme;
			this.$vuetify.theme.global.name.value = savedTheme;
		}

		const savedFullscreen = localStorage.getItem('captaincore-terminal-fullscreen');
		if (savedFullscreen !== null) {
			this.view_console.fullscreen = (savedFullscreen === 'true');
		}
		axios.interceptors.response.use(
			response => response,
			error => {
				const { config, response: { status }} = error;
				const originalRequest = config;
				if (error.response.status === 403 && error.response.data.code == "rest_cookie_invalid_nonce" ) {
					if ( this.wp_nonce_retry ) {
						this.goToPath( '/login' )
						this.wp_nonce_retry = false
						return
					}
					// Attempt to retrieve a valid token
					return axios.get( '/' ).then(response => {
						html = response.data
						const regex = /var wpApiSettings.+"nonce":"(.+)"/
						const found = html.match(regex);
						if ( typeof found[1] !== 'undefined' ) {
							this.wp_nonce_retry = true
							this.wp_nonce = found[1]
							originalRequest.headers["X-WP-Nonce"] = found[1]
							return axios(originalRequest);
						}
					})
					return Promise.reject(error);
				}
			});
		window.addEventListener('popstate', () => {
			path = window.location.pathname.replace( this.configurations.path, "/" )
			this.updateRoute( path )
		})
		 window.addEventListener('keydown', (e) => {
			// Check for Cmd (Mac) or Ctrl (Windows/Linux) + K
			if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
				e.preventDefault(); // Prevent browser search bar from opening
				
				// Toggle terminal visibility
				this.view_console.terminal_open = !this.view_console.terminal_open;
				if (this.view_console.terminal_open) {
					this.view_console.show = true;
				}
			}
			if (e.key === 'Escape') {
				// If Esc is pressed, close terminal and hide the activity island
				this.view_console.terminal_open = false;
				this.view_console.show = false;
			}
		});
		if ( typeof wpApiSettings == "undefined" ) {
			window.history.pushState( {}, 'login', window.location.origin + this.configurations.path + 'login' )
			this.route = "login"
			return
		} else {
			this.wp_nonce = wpApiSettings.nonce
		}
		if ( this.socket == "/ws" ) {
			console.log("Socket not defined")
			window.history.pushState( {}, 'connect', "/connect" )
			this.route = "connect"
			return
		}
		this.checkRequestedSites()
		this.fetchAccounts()
		this.fetchRecipes()
		this.fetchProviderActions()
		this.fetchEnvironments()
		if ( this.role == 'administrator' || this.role == 'owner' ) {
			this.fetchAccountPortals()
			this.fetchProcesses()
		}
		path = window.location.pathname.replace( this.configurations.path, "/" )
		this.updateRoute( path )

		if ( this.route == "" ) {
			this.triggerRoute()
		}

		// Start chat if logged in
		if ( this.role != 'administrator' && this.configurations.intercom_embed_id != "" && this.current_user_email != "" && this.current_user_login != "" && this.current_user_registered != "" ) {
			window.Intercom("boot", {
				app_id: this.configurations.intercom_embed_id,
				name: this.current_user_display_name,
				email: this.current_user_email,
				created_at: this.current_user_registered,
				user_hash: this.current_user_hash
			});
		}
	},
	computed: {
		groupedPushTargets() {
			// Ensure targets is always an array
			const targetsArray = Array.isArray(this.dialog_push_to_other.targets) ? this.dialog_push_to_other.targets : [];

			if (targetsArray.length === 0) {
				return {};
			}
			return targetsArray.reduce((acc, envTarget) => {
				// Ensure essential properties exist before trying to group
				const siteId = envTarget?.site_id;
				const siteName = envTarget?.name || 'Unknown Site'; // Default name
				const environmentName = envTarget?.environment || 'Unknown Env'; // Default name
				const environmentId = envTarget?.environment_id; // CaptainCore Env ID
				const homeUrl = envTarget?.home_url || siteName; // Fallback URL

				// Skip if crucial IDs are missing
				if (!siteId || !environmentId) {
					console.warn("Skipping push target due to missing site_id or environment_id:", envTarget);
					return acc;
				}

				if (!acc[siteId]) {
					acc[siteId] = {
						site_id: siteId,
						name: siteName,
						environments: []
					};
				}
				// Add the environment details
				acc[siteId].environments.push({
					environment_id: environmentId,
					name: environmentName,
					home_url: homeUrl
				});
				return acc;
			}, {});
		},
		filteredPushTargets() {
            // Ensure targets is always an array
            const targetsArray = Array.isArray(this.dialog_push_to_other.targets) ? this.dialog_push_to_other.targets : [];

            if (targetsArray.length === 0) {
                return [];
            }

            // Flatten the structure: [{site_id, site_name, environment_id, env_name, home_url}, ...]
            let flatTargets = targetsArray.map(envTarget => ({
                    site_id: envTarget?.site_id,
                    site_name: envTarget?.name || 'Unknown Site',
                    environment_id: envTarget?.environment_id,
                    env_name: envTarget?.environment || 'Unknown Env',
                    home_url: envTarget?.home_url || envTarget?.name || 'Unknown URL'
                })).filter(target => target.site_id && target.environment_id); // Ensure basic validity


            // Apply search filter
            if (!this.dialog_push_to_other.search) {
                return flatTargets; // Return full flat list if no search
            }

            const searchLower = this.dialog_push_to_other.search.toLowerCase();
            return flatTargets.filter(target => {
                const siteNameMatch = target.site_name.toLowerCase().includes(searchLower);
                const homeUrlMatch = target.home_url && target.home_url.toLowerCase().includes(searchLower);
                return siteNameMatch || homeUrlMatch;
            });
        },
        paginatedPushTargets() {
            const start = (this.dialog_push_to_other.currentPage - 1) * this.dialog_push_to_other.itemsPerPage;
            const end = start + this.dialog_push_to_other.itemsPerPage;
            // Use the flat filteredPushTargets array here
            return this.filteredPushTargets.slice(start, end);
        },
        totalPagesPushTargets() {
             return Math.ceil(this.filteredPushTargets.length / this.dialog_push_to_other.itemsPerPage);
        },
		filteredRecipeMenuRecipes() {
			if (!this.view_console.recipe_menu_search) return this.recipes;
			const search = this.view_console.recipe_menu_search.toLowerCase();
			return this.recipes.filter(r => r.title.toLowerCase().includes(search));
		},
		filteredSystemToolsMenu() {
			if (!this.view_console.recipe_menu_search) return this.system_tools;
			const search = this.view_console.recipe_menu_search.toLowerCase();
			return this.system_tools.filter(t => t.title.toLowerCase().includes(search));
		},
		filteredMailgunDeployTargets() {
			if (!this.dialog_domain.connected_sites || this.dialog_domain.connected_sites.length === 0) {
				return [];
			}
			if (!this.dialog_mailgun_deploy.search) {
				return this.dialog_domain.connected_sites;
			}
			const searchLower = this.dialog_mailgun_deploy.search.toLowerCase();
			return this.dialog_domain.connected_sites.filter(site => {
				const nameMatch = site.name && site.name.toLowerCase().includes(searchLower);
				const urlMatch = site.home_url && site.home_url.toLowerCase().includes(searchLower);
				const envMatch = site.environment && site.environment.toLowerCase().includes(searchLower);
				return nameMatch || urlMatch || envMatch;
			});
		},
		paginatedMailgunDeployTargets() {
			const start = (this.dialog_mailgun_deploy.currentPage - 1) * this.dialog_mailgun_deploy.itemsPerPage;
			const end = start + this.dialog_mailgun_deploy.itemsPerPage;
			return this.filteredMailgunDeployTargets.slice(start, end);
		},
		totalPagesMailgunDeployTargets() {
			return Math.ceil(this.filteredMailgunDeployTargets.length / this.dialog_mailgun_deploy.itemsPerPage);
		},
		currentSiteThumbnail() {
			const site = this.dialog_site.site;
			// Check if required data exists
			if (!site || !site.site_id || !site.screenshot_base) {
				return null;
			}
			// Build URL
			return `${this.remote_upload_uri}${site.site}_${site.site_id}/production/screenshots/${site.screenshot_base}_thumb-100.jpg`;
		},
		currentEnvironmentAction() {
			// Returns the active provider action (job) if it targets the currently selected environment
			if (!this.dialog_site.environment_selected || !this.provider_actions.length) {
				return null;
			}
			
			const currentEnvId = this.dialog_site.environment_selected.environment_id;

			// Find an action that is 'started' or 'waiting' (not done) that matches this env ID
			// Note: provider_actions is populated by fetchProviderActions/checkProviderActions from the DB
			const matchingAction = this.provider_actions.find(item => {
				const actionData = item.action; // This is the JSON decoded 'action' column
				if (!actionData) return false;

				// Check generic push
				if (actionData.command === 'push_environment' && actionData.target_environment_id == currentEnvId) {
					return true;
				}

				// Check specific Kinsta staging push (legacy logic fallback)
				if (actionData.command === 'deploy-to-staging' && actionData.site_id == this.dialog_site.site.site_id && this.dialog_site.environment_selected.environment === 'Staging') {
					return true;
				}

				// Check specific Kinsta production push (legacy logic fallback)
				if (actionData.command === 'deploy-to-production' && actionData.site_id == this.dialog_site.site.site_id && this.dialog_site.environment_selected.environment === 'Production') {
					return true;
				}

				return false;
			});

			return matchingAction ? matchingAction.action : null;
		},
		hasProviderActions() {
			return this.provider_actions.length > 0;
		},
		unassignedSiteCount() {
			let count = 0
			this.sites.forEach( s => {
				if  ( s.account_id == "" || s.account_id == "0" ) {
					count++
				}
			})
			return count
		},
		 themeFiltersApplied() {
			return this.applied_theme_filters.length > 0;
		},
		pluginFiltersApplied() {
			return this.applied_plugin_filters.length > 0;
		},
		coreFiltersApplied() {
			return this.applied_core_filters.length > 0;
		},
		isAnySiteFilterActive() {
			return this.isUnassignedFilterActive || (this.search && this.search.length > 0) || (this.combinedAppliedFilters && this.combinedAppliedFilters.length > 0) || this.coreFiltersApplied;
		},
		combinedAppliedFilters() {
			return [...this.applied_theme_filters, ...this.applied_plugin_filters];
		},
		isAnyAccountFilterActive() {
			return this.isOutstandingFilterActive || this.isEmptyFilterActive || (this.account_search && this.account_search.length > 0);
		},
		emptyAccountCount() {
			let count = 0;
			this.accounts.forEach(account => {
				if (account.metrics.users === 0 && account.metrics.sites === 0 && account.metrics.domains === 0) {
					count++;
				}
			});
			return count;
		},
		totalAdvancedFilters() {
			if (!this.combinedAppliedFilters) {
				return 0;
			}
			const primaryCount = this.combinedAppliedFilters.length;
			const secondaryCount = this.combinedAppliedFilters.reduce((acc, filter) => {
				return acc + (filter.selected_versions?.length || 0) + (filter.selected_statuses?.length || 0);
			}, 0);
			return primaryCount + secondaryCount;
		},
		filteredAccountsData() {
			let filtered = this.accounts.filter(account => account.filtered);

			if (this.isOutstandingFilterActive) {
				filtered = filtered.filter(account => account.metrics.outstanding_invoices && account.metrics.outstanding_invoices > 0);
			}

			if (this.isEmptyFilterActive) {
				filtered = filtered.filter(account => account.metrics.users === 0 && account.metrics.sites === 0 && account.metrics.domains === 0);
			}

			const searchLower = this.account_search ? this.account_search.toLowerCase() : '';
			if (searchLower) {
				filtered = filtered.filter(account => {
					const nameMatch = account.name && account.name.toLowerCase().includes(searchLower);
					return nameMatch;
				});
			}
			return filtered;
		},
		filteredSites() {
			// Start with sites filtered by the server-side logic (e.g. unassigned, initial load)
			let filtered = this.sites.filter(site => site.filtered);

			// Apply Unassigned Filter
			if (this.isUnassignedFilterActive) {
				filtered = filtered.filter(site => site.account_id === "" || site.account_id === "0");
			}

			// Apply Client-Side filtering based on environments
			// A site is visible if AT LEAST ONE of its environments matches the criteria.
			
			// Optimizations:
			// If no search and no filters, show everything returned by base logic
			if (!this.search && this.combinedAppliedFilters.length === 0) {
				return filtered;
			}

			return filtered.filter(site => {
				// Fallback for sites with no environments array (shouldn't happen with new logic, but safe)
				if (!site.environments || site.environments.length === 0) return false;

				// Check if ANY environment matches
				return site.environments.some(env => this.isEnvironmentMatched(env));
			});
		},
		runShortcutLabel() {
			const isMac = typeof window !== 'undefined' && 
						(navigator.platform.toUpperCase().indexOf('MAC') >= 0 || 
						navigator.userAgent.toUpperCase().indexOf('MAC') >= 0);
			return isMac ? '⌘⏎' : 'Ctrl+↵';
		},
		flattenedSiteEnvironments() {
			let flattened = [];
			this.filteredSites.forEach(site => {
				const visibleEnvs = this.getVisibleEnvironments(site);
				visibleEnvs.forEach(env => {
					// Create a shallow copy of the site object so we don't mutate the original
					let entry = Object.assign({}, site);
					// Attach the specific environment data to this entry for the view to consume
					entry.current_env = env;
					// Create a unique key for list rendering
					entry.unique_key = env.environment_id;
					flattened.push(entry);
				});
			});
			return flattened;
		},
		oustandingAccountCount() {
			let count = 0
			this.accounts.forEach( account => {
				if ( account.metrics.outstanding_invoices && account.metrics.outstanding_invoices > 0 ) {
					count++
				}
			})
			return count
		},
		totalArchivesSize() {
			// Sum up the 'size' of all items in the archives array
			return this.archives.reduce((total, item) => total + (item.size || 0), 0);
		},
		filteredEnvatoThemes() {
			let themes = this.new_theme.envato.items
			if ( this.new_theme.envato.search != "" ) {
				themes = themes.filter( theme => {
					return theme.name.toLowerCase().includes( this.new_theme.envato.search.toLowerCase() )
				})
			}
			return themes
		},
		filteredEnvatoPlugins() {
			let plugins = this.new_plugin.envato.items
			if ( this.new_plugin.envato.search != "" ) {
				plugins = plugins.filter( plugin => {
					return plugin.name.toLowerCase().includes( this.new_plugin.envato.search.toLowerCase() )
				})
			}
			return plugins
		},
		keySelections() {
			keys = JSON.parse ( JSON.stringify (  this.keys ) )
			keys.push( { key_id: "use_password", title: "Use SFTP Password" } )
			return keys
		},
		gravatar() {
			return 'https://www.gravatar.com/avatar/' + md5( this.current_user_email.trim().toLowerCase() ) + '?s=80&d=mp'
		},
		fetchInvite() {
			var urlParams = new URLSearchParams( this.querystring )
			var invite = { account: urlParams.get('account'), token: urlParams.get('token') }
			return invite
		},
		selected_default_recipes() {
			if ( typeof this.dialog_account.records.account.defaults.recipes == 'undefined' ) {
				return "";
			} else {
				return this.dialog_account.records.account.defaults.recipes;
			}
		},
		dialogCapturesPagesText() {
			if ( typeof this.dialog_captures.capture.pages == 'undefined' ) {
				return ""
			}
			if ( this.dialog_captures.capture.pages.length == 1 ) {
				return "Page"
			} else {
				return "Pages"
			}
		},
		billingAddress() {
			billing_address = this.billing.address.address_1 + "<br />"
			if ( this.billing.address.address_2 != "" ) {
				billing_address += `${this.billing.address.address_2}<br />`
			}
			billing_address += `${this.billing.address.city}, ${ this.billing.address.state }  ${ this.billing.address.postcode }<br />`
			if ( this.billing.address.country != "" ) {
				countries = this.countries.filter( c => c.value == this.billing.address.country )
				billing_address += countries.map( c => c.text ).join(" ")
			}
			
			return billing_address
		},
		filterSitesWithErrors() {
			return this.sites.filter( s => s.console_errors != "" )
		},
		filterSitesWithConnectionErrors() {
			return this.sites.filter( s => s.connection_errors != "" )
		},
		filterCount() {
			return this.combinedAppliedFilters.length;
		},
		runningJobs() {
			return this.jobs.filter(job => job.status != 'done' && job.status != 'error' ).length;
		},
		completedJobs() {
			return this.jobs.filter(job => job.status == 'done' || job.status == 'error' ).length;
		},
		filteredRecipes() {
			return this.recipes.filter( recipe => recipe.user_id != 'system' );
		},
		totalEnvironmentsCount() {
			return this.sites.reduce((acc, site) => acc + (site.environments ? site.environments.length : 0), 0);
		},
		filteredEnvironmentsCount() {
			let count = 0;
			this.filteredSites.forEach(site => {
				site.environments.forEach(env => {
					if (this.isEnvironmentMatched(env)) count++;
				});
			});
			return count;
		},
		filteredSitesCount() {
			// Counts how many individual environments match the current filters
			let count = 0;
			this.filteredSites.forEach(site => {
				if (!site.environments) return;
				site.environments.forEach(env => {
					if (this.isEnvMatch(env)) count++;
				});
			});
			return count;
		},
		filteredQuickRunRecipes() {
			if (!this.view_console.search) return this.recipes;
			const search = this.view_console.search.toLowerCase();
			return this.recipes.filter(r => r.title.toLowerCase().includes(search));
		},
		activeJobDescription() {
			const active = this.jobs.find(j => j.status === 'running') || this.jobs[this.jobs.length - 1];
			return active ? active.description : '';
		},
		activeJobLastLine() {
			const active = this.jobs.find(j => j.status === 'running') || this.jobs[this.jobs.length - 1];
			if (active && active.stream && active.stream.length > 0) {
				return active.stream[active.stream.length - 1];
			}
			return '';
		},
		filteredConsoleTargets() {
			const search = this.view_console.target_search ? this.view_console.target_search.toLowerCase() : '';
			
			if (!search) return this.environments;

			return this.environments.filter(env => {
				const url = env.home_url || '';
				const name = env.name || '';
				const envType = env.environment || '';
				
				return url.toLowerCase().includes(search) || 
					name.toLowerCase().includes(search) || 
					envType.toLowerCase().includes(search);
			});
		},
		isTargetSelected() {
			return (env) => {
				return this.view_console.selected_targets.some(t => t.environment_id == env.environment_id);
			};
		},
		allFilteredConsoleTargets() {
			const search = this.view_console.target_search ? this.view_console.target_search.toLowerCase() : '';
			
			if (!search) return this.environments;

			return this.environments.filter(env => {
				const url = env.home_url || '';
				const name = env.name || '';
				const envType = env.environment || '';
				
				return url.toLowerCase().includes(search) || 
					name.toLowerCase().includes(search) || 
					envType.toLowerCase().includes(search);
			});
		},
		displayedConsoleTargets() {
			return this.allFilteredConsoleTargets.slice(0, this.view_console.target_limit);
		},
		dnsRecords() {
			count = 0;
			this.dialog_domain.records.forEach( r => {
				if ( r.update.record_status == 'new-record' ) {
					return
				}
				if ( typeof r.value === 'string' ) {
					count = count + 1;
				}
				if ( typeof r.value === 'object' ) {
					count = count + r.value.length
				}
			})
			return count;
		},
		allDomains() {
			return Object.keys( this.domains ).length;
		},
		plan_usage_pricing_sites() {
			let unit_price = 0;
			extra_sites = parseInt( this.dialog_account.records.account.plan.usage.sites ) - parseInt( this.dialog_account.records.account.plan.limits.sites )
			if ( extra_sites > 0 ) {
				unit_price = this.configurations.usage_pricing.sites.cost
				if ( this.configurations.usage_pricing.sites.interval != this.dialog_account.records.account.plan.interval ) {
					unit_price = this.configurations.usage_pricing.sites.cost / this.configurations.usage_pricing.sites.interval
					unit_price = unit_price * this.dialog_account.records.account.plan.interval
				}
			}
			return unit_price
		},
		plan_usage_pricing_storage() {
			let unit_price = 0;
			extra_storage = ( parseInt( this.dialog_account.records.account.plan.usage.storage ) / 1024 / 1024 / 1024 ) - parseInt( this.dialog_account.records.account.plan.limits.storage ) 
			if ( extra_storage > 0 ) {
				unit_price = this.configurations.usage_pricing.storage.cost
				if ( this.configurations.usage_pricing.storage.interval != this.dialog_account.records.account.plan.interval ) {
					unit_price = this.configurations.usage_pricing.storage.cost / this.configurations.usage_pricing.storage.interval
					unit_price = unit_price * this.dialog_account.records.account.plan.interval
				}
			}
			return unit_price
		},
		plan_usage_pricing_visits() {
			let unit_price = 0;
			extra_visits = Math.ceil ( ( parseInt( this.dialog_account.records.account.plan.usage.visits ) - parseInt( this.dialog_account.records.account.plan.limits.visits ) ) / parseInt ( this.configurations.usage_pricing.traffic.quantity ) )
			if ( extra_visits > 0 ) {
				unit_price = this.configurations.usage_pricing.traffic.cost
				if ( this.configurations.usage_pricing.traffic.interval != this.dialog_account.records.account.plan.interval ) {
					unit_price = this.configurations.usage_pricing.traffic.cost / this.configurations.usage_pricing.traffic.interval
					unit_price = unit_price * this.dialog_account.records.account.plan.interval
				}
			}
			return parseInt( unit_price )
		},
		plan_usage_estimate() {
			if ( typeof this.dialog_account.records.account.plan == 'object' ) {
				const plan = this.dialog_account.records.account.plan;
				let extras = 0
				let addons = 0
				let credits = 0
				let charges = 0
				let base_cost = 0

				// Calculate Addons, Credits, Charges (Common to both modes)
				if ( plan.addons ) {
					plan.addons.forEach( item => {
						if ( item.price != "" ) {
							addons = addons + parseFloat( ( item.quantity * item.price ).toFixed(2) )
						}
					})
				}
				if ( plan.credits ) {
					plan.credits.forEach( item => {
						if ( item.price != "" ) {
							credits = credits + parseFloat( ( item.quantity * item.price ).toFixed(2) )
						}
					})
				}
				if ( plan.charges ) {
					plan.charges.forEach( item => {
						if ( item.price != "" ) {
							charges = charges + parseFloat( ( item.quantity * item.price ).toFixed(2) )
						}
					})
				}

				// Determine Interval Label
				let units = [] 
				units[1] = "month"
				units[3] = "quarter"
				units[6] = "biannually"
				units[12] = "year"
				let unit = units[ plan.interval ]

				// Check Billing Mode
				let billing_mode = plan.billing_mode || 'standard';

				if ( billing_mode === 'per_site' ) {
					// --- Per Site Mode Logic ---
					// Price is treated as "Price Per Site"
					let site_count = parseInt( plan.usage.sites || 0 );
					let price_per_site = parseFloat( plan.price || 0 );
					
					base_cost = site_count * price_per_site;
					// Extras (overages) remain 0 in this mode
				} else {
					// --- Standard Mode Logic ---
					// Price is treated as "Base Plan Price" + Overages
					base_cost = parseFloat( plan.price || 0 );

					let extra_sites = parseInt( plan.usage.sites ) - parseInt( plan.limits.sites )
					let extra_storage = Math.ceil ( ( ( parseInt( plan.usage.storage ) / 1024 / 1024 / 1024 ) - parseInt( plan.limits.storage ) ) / 10 )
					let extra_visits = Math.ceil ( ( parseInt( plan.usage.visits ) - parseInt( plan.limits.visits ) ) / parseInt ( this.configurations.usage_pricing.traffic.quantity ) )

					if ( extra_sites > 0 ) {
						let unit_price = this.configurations.usage_pricing.sites.cost
						if ( this.configurations.usage_pricing.sites.interval != plan.interval ) {
							unit_price = this.configurations.usage_pricing.sites.cost / this.configurations.usage_pricing.sites.interval
							unit_price = unit_price * plan.interval
						}
						extras = extras + ( extra_sites * unit_price )
					}
					if ( extra_storage > 0 ) {
						let unit_price = this.configurations.usage_pricing.storage.cost
						if ( this.configurations.usage_pricing.storage.interval != plan.interval ) {
							unit_price = this.configurations.usage_pricing.storage.cost / this.configurations.usage_pricing.storage.interval
							unit_price = unit_price * plan.interval
						}
						extras = extras + ( extra_storage * unit_price )
					}
					if ( extra_visits > 0 ) {
						let unit_price = this.configurations.usage_pricing.traffic.cost
						if ( this.configurations.usage_pricing.traffic.interval != plan.interval ) {
							unit_price = this.configurations.usage_pricing.traffic.cost / this.configurations.usage_pricing.traffic.interval
							unit_price = unit_price * plan.interval
						}
						extras = extras + ( extra_visits * unit_price )
					}
				}

				// Final Calculation
				let total = ( parseFloat( addons ) + parseFloat( charges ) - parseFloat( credits ) + parseFloat( extras ) + parseFloat( base_cost ) ).toFixed(2)
				
				if ( total < 0 ) {
					total = 0;
				}
				
				let output = `$${total}`;
				if ( typeof unit != 'undefined' ) {
					output += ` <small>per ${unit}</small>`;
				}
				return output;
			}
			return ""
		},
	},
	methods: {
		toggleTheme() {
			this.theme = this.theme === 'light' ? 'dark' : 'light';
			this.$vuetify.theme.global.name.value = this.theme;
			localStorage.setItem('captaincore-theme', this.theme);
		},
		updateRoute( href ) {
			// Remove trailing slash
			if ( href.length > 1 && href.slice(-1) == "/" ) {
				href = href.slice(0, -1)
			}
			// Catch all nested routes to their parent route.
			if ( href.match(/\//g).length > 1 ) {
				this.route_path = href.split('/').slice( 2 ).join( "/" )
				href = href.split('/').slice( 0, 2 ).join( "/" )
			} else {
				this.route_path = ""
			}
			this.route = this.routes[ href ]
		},
		triggerRoute() {
			if (this.route_path === "") {
				this.dialog_domain.step = 1;
				this.dialog_site.step = 1;
				this.dialog_account.step = 1;
				this.dialog_billing.step = 1;
			}

			if (this.socket == "/ws") {
				window.history.pushState({}, 'login', "/connect");
				this.route = "connect";
				this.loading_page = false;
				return;
			}
			if (this.wp_nonce == "") {
				window.history.pushState({}, 'login', window.location.origin + this.configurations.path + 'login');
				this.route = "login";
				this.loading_page = false;
				return;
			}

			// Static Routes
			if (["login", "connect", "cookbook", "handbook", "profile"].includes(this.route)) {
				this.selected_nav = "";
				this.loading_page = false;
			}

			if (this.route == "archives") {
				this.selected_nav = "";
				this.fetchArchives();
			}

			if (this.route == "domains") {
				if (this.allDomains == 0) this.loading_page = true;
				this.selected_nav = "domains";
				this.fetchDomains();
			}

			if (this.route == "users") {
				this.selected_nav = "";
				this.fetchAllUsers();
			}

			if (this.route == "keys") {
				this.selected_nav = "";
				this.loading_page = false;
				this.fetchKeys();
			}

			if (this.route == "defaults") {
				this.selected_nav = "";
				this.loading_page = false;
				this.fetchDefaults();
			}

			if (this.route == "accounts") {
				this.selected_nav = "accounts";
				this.loading_page = false;
			}

			if (this.route == "billing") {
				this.fetchBilling();
				this.selected_nav = "billing";
				this.loading_page = false;
			}

			if (this.route == "subscriptions") {
				this.fetchSubscriptions();
				this.selected_nav = "";
				this.loading_page = false;
			}

			if (this.route == "configurations") {
				this.fetchConfigurations();
				this.fetchProviders();
				this.loading_page = false;
				this.selected_nav = "";
			}

			if (this.route == "sites") {
				if (this.sites.length == 0) this.loading_page = true;
				this.selected_nav = "sites";
				this.fetchSites();
			}

			if (this.route == "health") {
				if (this.sites.length == 0) this.loading_page = true;
				this.selected_nav = "";
				this.fetchSites();
			}

			if (this.route == "vulnerability-scans") {
				this.fetchVulnerabilityScans();
			}

			if (this.fetchInvite.account) {
				this.fetchInviteInfo();
				this.route = "invite";
				this.loading_page = false;
				return;
			}

			if (this.route == "") {
				if (this.sites.length == 0) this.loading_page = true;
				this.route = "sites";
				this.selected_nav = "sites";
			}
		},
		triggerPath() {
			if (this.route_path === "") {
				this.dialog_domain.step = 1;
				this.dialog_site.step = 1;
				this.dialog_account.step = 1;
				this.dialog_billing.step = 1;
				return;
			}

			if (this.route === "domains") {
				this.dialog_domain.step = 2;
				const domain = this.domains.find(d => d.domain_id == this.route_path);
				if (domain) {
					this.modifyDNS(domain);
					this.fetchDomain(domain);
				}
				return;
			}

			if (this.route === "accounts") {
				this.dialog_account.step = 2;
				const account = this.accounts.find(a => a.account_id == this.route_path);
				if (account) {
					this.showAccount(account.account_id);
				}
				return;
			}

			if (this.route === "billing") {
				this.dialog_billing.step = 2;
				this.showInvoice(this.route_path);
				return;
			}

			if (this.route === "sites") {
				
				// Handle "Add New" route
				if (this.route_path === "new") {
					this.dialog_site.step = 3;
					return;
				}

				// Parse ID and Action (e.g. "155/addons" -> id: 155, action: "addons")
				const parts = this.route_path.split('/');
				const siteId = parts[0];
				const action = parts[1] || 'info'; // Default to info if no action exists

				const site = this.sites.find(s => s.site_id == siteId);
				if (!site) return;

				// Configuration map for all site sub-pages
				const actionMap = {
					'info':             { tab: 'info' }, // Default
					'addons':           { tab: 'addons' },
					'logs':             { tab: 'logs' },
					'scripts':          { tab: 'scripts' },
					'visual-captures':  { tab: 'visual-captures' },
					
					// Actions with specific API callbacks
					'stats':            { tab: 'stats', callback: () => this.fetchStats() },
					'updates':          { tab: 'updates', callback: () => this.viewUpdateLogs(siteId) },
					'users':            { tab: 'users', callback: () => this.fetchUsers() },
					
					// Backups have specific sub-steps
					'backup-overview':  { tab: 'backups', backupStep: 1 },
					'backups':          { tab: 'backups', backupStep: 2, callback: () => this.viewBackups() },
					'quicksaves':       { tab: 'backups', backupStep: 3, callback: () => this.viewQuicksaves() },
					'snapshots':        { tab: 'backups', backupStep: 4, callback: () => this.viewSnapshots() },
				};

				const config = actionMap[action];

				if (config) {
					this.dialog_site.step = 2;
					
					// Load the site data and switch tabs
					this.showSite(site, config.tab);

					// Handle specific backup steps
					if (config.backupStep) {
						this.dialog_site.backup_step = config.backupStep;
					}

					// Run specific data fetchers if required
					if (config.callback) {
						config.callback();
					}
				}
			}
		},
		goToPath( href ) {
			this.updateRoute( href )
			path = this.configurations.path.endsWith('/') ? this.configurations.path.slice(0,-1) : this.configurations.path
			window.history.pushState( {}, this.routes[ href ], window.location.origin + path + href )
		},
		resetPassword() {
			this.login.loading = true
			if ( ! this.$refs.reset.validate() ) {
				this.login.loading = false
				return
			}
			axios.post( '/wp-json/captaincore/v1/login/', {
					'command': "reset",
					'login': this.login
				})
				.then( response => {
					this.login.message = "A password reset email is on it's way."
					this.login.loading = false
				})
				.catch(error => {
					console.log(error);
				});
		},
		signIn() {
			this.login.loading = true
			if ( ! this.$refs.login.validate() ) {
				this.login.loading = false
				return
			}
			axios.post( '/wp-json/captaincore/v1/login/', {
					'command': "signIn",
					'login': this.login
				})
				.then( response => {
					if ( typeof response.data.errors === 'undefined' && typeof response.data.info === 'undefined' ) {
						window.location = window.location.origin + this.configurations.path
						return
					}
					this.login.errors = response.data.errors
					this.login.info = response.data.info
					this.login.loading = false
				})
				.catch(error => {
					console.log(error);
				});
		},
		signOut() {
			axios.post( '/wp-json/captaincore/v1/login/', {
				command: "signOut" 
			})
			.then( response => {
				window.location = window.location.origin + this.configurations.path + 'login'
				this.route = "login"
				this.wp_nonce = "";
			})
		},
		fetchArchives() {
			this.archives_loading = true;
			axios.get('/wp-json/captaincore/v1/archive', {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				// Map keys to lowercase to avoid case-sensitivity issues in DOM templates
				this.archives = response.data.map( item => {
					return {
						name: item.Name,
						size: item.Size,
						mod_time: item.ModTime,
						path: item.Path
					}
				});
				this.archives_loading = false;
				this.loading_page = false;
			})
			.catch(error => {
				console.error("Error fetching archives:", error);
				this.snackbar.message = "Failed to load archives.";
				this.snackbar.show = true;
				this.archives_loading = false;
			});
		},
		generateArchiveLink(item) {
			this.dialog_archive_link.show = true;
			this.dialog_archive_link.loading = true;
			this.dialog_archive_link.url = "";

			// The backend endpoint expects a POST with 'file' param
			axios.post('/wp-json/captaincore/v1/archive/share', {
				file: item.path
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.dialog_archive_link.url = response.data.link;
				this.dialog_archive_link.loading = false;
			})
			.catch(error => {
				console.error("Error generating link:", error);
				this.snackbar.message = "Failed to generate link.";
				this.snackbar.show = true;
				this.dialog_archive_link.show = false;
			});
		},
		getAllNodePaths(nodes) {
			let allPaths = [];
			for (const node of nodes) {
				allPaths.push(node.path); // Add the parent node's path
				if (node.children && node.children.length > 0) {
					allPaths = allPaths.concat(this.getAllNodePaths(node.children)); // Recursively add children paths
				}
			}
			return allPaths;
		},
		selectAllInBackup(item) {
			const allPaths = this.getAllNodePaths(this.backup_set_files);
			// Update both the v-model and the internal tracker to prevent
			// the @update:selected handler from misinterpreting the change.
			item.tree = allPaths;
			item.lastCalculatedTree = allPaths; 

			// Calculate the total size directly from the raw, top-level data.
			// This bypasses the selection-based calculation and gives the true total.
			let totalSize = 0;
			for (const rootNode of this.backup_set_files) {
				totalSize += this.getActualNodeSize(rootNode); // getActualNodeSize is already recursive
			}
			item.calculated_total = totalSize;
		},
		formatProviderLabel( item ) {
			provider = this.formatProvider( item.provider );
			return `${item.name} (${provider})`;
		},
		copyText( value ) {
			// Use modern Clipboard API if available (works better inside Dialogs)
			if (navigator.clipboard) {
				navigator.clipboard.writeText(value).then(() => {
					this.snackbar.message = "Copied to clipboard.";
					this.snackbar.show = true;
				}).catch(err => {
					console.error('Async: Could not copy text: ', err);
					// Fallback if async fails
					this.copyTextFallback(value);
				});
			} else {
				// Fallback for older browsers
				this.copyTextFallback(value);
			}
		},
		copyTextFallback( value ) {
			var clipboard = document.getElementById("clipboard");
			clipboard.value = value;
			clipboard.focus();
			clipboard.select();
			try {
				document.execCommand("copy");
				this.snackbar.message = "Copied to clipboard.";
				this.snackbar.show = true;
			} catch (err) {
				console.error('Fallback: Oops, unable to copy', err);
				this.snackbar.message = "Failed to copy to clipboard.";
				this.snackbar.show = true;
			}
		},
		copyJobStream(job) {
			if (!job.stream || job.stream.length === 0) return;
			
			// Exclude the last line and join
			const textToCopy = job.stream.slice(0, -1).join('\n');
			
			this.copyText(textToCopy);
		},
		copyInviteLink( account, token ) {
			link = window.location.origin + window.location.pathname + `?account=${account}&token=${token}`
			this.copyText( link )
		},
		copySFTP( key ) {
			sftp_info = `Address: ${key.address}\nUsername: ${key.username}\nPassword: ${key.password}\nProtocol: ${key.protocol}\nPort: ${key.port}`
			this.copyText( sftp_info );
		},
		copyDatabase( key ) {
			database_info = `Database: ${key.database}\nDatabase Username: ${key.database_username}\nDatabase Password: ${key.database_password}`
			this.copyText( database_info );
		},
		triggerEnvironmentUpdate(){
			if ( this.dialog_site.site.tabs == "tab-Site-Management" && this.dialog_site.site.tabs_management == "tab-Stats" ) {
				this.fetchStats()
			}
			if ( this.dialog_site.site.tabs == "tab-Site-Management" && this.dialog_site.site.tabs_management == "tab-Updates" ) {
				this.viewUpdateLogs()
			}
			if ( this.dialog_site.site.tabs == "tab-Site-Management" && this.dialog_site.site.tabs_management == "tab-Backups" && this.dialog_site.backup_step == 2 ) {
				this.viewBackups()
			}
			if ( this.dialog_site.site.tabs == "tab-Site-Management" && this.dialog_site.site.tabs_management == "tab-Backups" && this.dialog_site.backup_step == 3 ) {
				this.viewQuicksaves()
			}
			if ( this.dialog_site.site.tabs == "tab-Site-Management" && this.dialog_site.site.tabs_management == "tab-Backups" && this.dialog_site.backup_step == 4 ) {
				this.viewSnapshots()
			}
		},
		closeVersionFilter(index) {
			this.$nextTick(() => {
				if (this.$refs.versionFilterRefs && this.$refs.versionFilterRefs[index]) {
					this.$refs.versionFilterRefs[index].blur();
				}
			});
			this.filterSites(); // Trigger filtering
		},
        closeStatusFilter(index) {
			this.$nextTick(() => {
				if (this.$refs.statusFilterRefs && this.$refs.statusFilterRefs[index]) {
					this.$refs.statusFilterRefs[index].blur();
				}
			});
			this.filterSites(); // Trigger filtering
		},
		user_name( user_id ) {
			users = this.users.filter( u => u.user_id == user_id )
			if ( users.length != 1 ) {
				return ""
			}
			return users[0].name
		},
		account_name( account_id ) {
			accounts = this.accounts.filter( a => a.account_id == account_id )
			if ( accounts.length != 1 ) {
				return ""
			}
			return accounts[0].name
		},
		revenue_estimated_total() {
			return "Total: $" + Object.values( this.revenue_estimated ).reduce((a, b) => a + b, 0)
		},
		my_plan_usage_estimate( plan ) {
			extras = 0
			addons = 0
			if ( plan.addons ) {
				plan.addons.forEach( addon => {
					if ( addon.price != "" ) {
						addons = addons + (  parseFloat( addon.quantity ) * parseFloat( addon.price ) )
					}
				})
			}
			total = parseFloat( addons ) + parseFloat( plan.price )
			units = [] 
			units[1] = "month"
			units[3] = "quarter"
			units[6] = "biannually"
			units[12] = "year"
			unit = units[ plan.interval ]
			extra_sites = parseInt( plan.usage.sites ) - parseInt( plan.limits.sites )
			extra_storage = Math.ceil ( ( ( parseInt( plan.usage.storage ) / 1024 / 1024 / 1024 ) - parseInt( plan.limits.storage ) ) / 10 )
			extra_visits = Math.ceil ( ( parseInt( plan.usage.visits ) - parseInt( plan.limits.visits ) ) / parseInt ( this.configurations.usage_pricing.traffic.quantity ) )
			if ( extra_sites > 0 ) {
				unit_price = this.configurations.usage_pricing.sites.cost
				if ( this.configurations.usage_pricing.sites.interval != plan.interval ) {
					unit_price = this.configurations.usage_pricing.sites.cost / this.configurations.usage_pricing.sites.interval
					unit_price = unit_price * plan.interval
				}
				extras = extras + ( extra_sites * unit_price )
			}
			if ( extra_storage > 0 ) {
				unit_price = this.configurations.usage_pricing.storage.cost
				if ( this.configurations.usage_pricing.storage.interval != plan.interval ) {
					unit_price = this.configurations.usage_pricing.storage.cost / this.configurations.usage_pricing.storage.interval
					unit_price = unit_price * plan.interval
				}
				extras = extras + ( extra_storage * unit_price )
			}
			if ( extra_visits > 0 ) {
				unit_price = this.configurations.usage_pricing.traffic.cost
				if ( this.configurations.usage_pricing.traffic.interval != plan.interval ) {
					unit_price = this.configurations.usage_pricing.traffic.cost / this.configurations.usage_pricing.traffic.interval
					unit_price = unit_price * plan.interval
				}
				extras = extras + ( extra_visits * unit_price )
			}
			total = parseFloat( addons ) + parseFloat( extras ) + parseFloat( plan.price )
			response = `$${total}`
			if ( typeof unit != 'undefined' ) {
				response += ` <small>per ${unit}</small>`
			}
			return response
		},
		resetColors() {
			this.currentThemeColors = {
				primary: '#1976D2',
				secondary: '#424242',
				accent: '#82B1FF',
				error: '#FF5252',
				info: '#2196F3',
				success: '#4CAF50',
				warning: '#FFC107'
			}
		},
		resetPortalColors() {
			this.dialog_account_portal.portal.colors = {
				primary: '#1976D2',
				secondary: '#424242',
				accent: '#82B1FF',
				error: '#FF5252',
				info: '#2196F3',
				success: '#4CAF50',
				warning: '#FFC107'
			}
		},
		sortTree( data, uniqueIdCounter = 0 ) {
			if ( ! data ) { return }
			for (let i = 0; i < data.length; i++) {
				const item = data[i];
				if (item && typeof item.name !== 'undefined' && item.name !== null) {
					item.name = String(item.name);
				} else if (item && (typeof item.name === 'undefined' || item.name === null)) {
					item.name = "";
				}
			}
            data.sort( (a, b) => a.type > b.type || a.name > b.name )
            for ( var i = 0; i< data.length; i++ ) {
                var val = data[i]
				val.id = uniqueIdCounter++;
                if ( val.children ) { this.sortTree( val.children, uniqueIdCounter ) }
            }
		},
		saveGlobalConfigurations() {
			this.dialog_configure_defaults.loading = true;
			this.configurations.colors = this.currentThemeColors
			// Prep AJAX request
			var data = {
				'action': 'captaincore_local',
				'command': "saveGlobalConfigurations",
				'value': this.configurations
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.snackbar.message = response.data
					this.snackbar.show = true
				})
				.catch(error => {
					this.snackbar.message = error.response
					this.snackbar.show = true
			});
		},
		saveGlobalDefaults() {
			this.dialog_configure_defaults.loading = true;
			// Prep AJAX request
			var data = {
				'action': 'captaincore_local',
				'command': "saveGlobalDefaults",
				'value': this.defaults
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.snackbar.message = response.data
					this.snackbar.show = true
				})
				.catch(error => {
					this.snackbar.message = error.response
					this.snackbar.show = true
			});
		},
		saveDefaults() {
			this.dialog_configure_defaults.loading = true;
			// Prep AJAX request
			var data = {
				'action': 'captaincore_local',
				'command': "saveDefaults",
				'value': this.dialog_account.records.account
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					console.log( response.data )
					this.dialog_configure_defaults.show = false;
					this.dialog_configure_defaults.loading = false;
				})
				.catch(error => {
					console.log(error.response)
			});
		},
		showDomainMappings() {
			this.dialog_domain_mappings.show = true;
			this.dialog_domain_mappings.loading = true;
			this.dialog_domain_mappings.errors = [];
			this.dialog_domain_mappings.new_domain = "";
			this.dialog_domain_mappings.site_id = this.dialog_site.site.site_id;
			this.dialog_domain_mappings.env_name = this.dialog_site.environment_selected.environment;
			this.fetchDomainMappings();
		},
		fetchDomainMappings() {
			this.dialog_domain_mappings.loading = true;
			const site_id = this.dialog_domain_mappings.site_id;
			const env_name = this.dialog_domain_mappings.env_name.toLowerCase();

			axios.get(`/wp-json/captaincore/v1/sites/${site_id}/${env_name}/domains`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.dialog_domain_mappings.domains = response.data;
				this.dialog_domain_mappings.loading = false;
			})
			.catch(error => {
				console.error("Error fetching domain mappings:", error);
				this.dialog_domain_mappings.errors = ["Failed to fetch domains. " + (error.response?.data?.message || error.message)];
				this.dialog_domain_mappings.loading = false;
			});
		},
		addDomainMapping() {
			if (!this.dialog_domain_mappings.new_domain) return;
			this.dialog_domain_mappings.loading = true;
			const site_id = this.dialog_domain_mappings.site_id;
			const env_name = this.dialog_domain_mappings.env_name.toLowerCase();

			axios.post(`/wp-json/captaincore/v1/sites/${site_id}/${env_name}/domains`, {
				domain_name: this.dialog_domain_mappings.new_domain
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.snackbar.message = `Domain '${this.dialog_domain_mappings.new_domain}' is being added.`;
				this.snackbar.show = true;
				this.dialog_domain_mappings.new_domain = "";
				// API is async, so we wait a bit before refetching
				setTimeout(this.fetchDomainMappings, 5000);
			})
			.catch(error => {
				console.error("Error adding domain mapping:", error);
				this.dialog_domain_mappings.errors = ["Failed to add domain. " + (error.response?.data?.message || error.message)];
				this.dialog_domain_mappings.loading = false;
			});
		},
		deleteDomainMapping(domain) {
			if (!confirm(`Are you sure you want to delete the domain '${domain.name}'? This cannot be undone.`)) return;
			this.dialog_domain_mappings.loading = true;
			const site_id = this.dialog_domain_mappings.site_id;
			const env_name = this.dialog_domain_mappings.env_name.toLowerCase();

			axios.delete(`/wp-json/captaincore/v1/sites/${site_id}/${env_name}/domains`, {
				headers: { 'X-WP-Nonce': this.wp_nonce },
				data: { domain_ids: [domain.id] }
			})
			.then(response => {
				this.snackbar.message = `Domain '${domain.name}' is being deleted.`;
				this.snackbar.show = true;
				// API is async, so we wait a bit before refetching
				setTimeout(this.fetchDomainMappings, 5000);
			})
			.catch(error => {
				console.error("Error deleting domain mapping:", error);
				this.dialog_domain_mappings.errors = ["Failed to delete domain. " + (error.response?.data?.message || error.message)];
				this.dialog_domain_mappings.loading = false;
			});
		},
		setPrimaryDomainMapping(domain) {
			if (!confirm(`Are you sure you want to set '${domain.name}' as the primary domain? This will run a search-and-replace on your site.`)) return;
			this.dialog_domain_mappings.loading = true;
			const site_id = this.dialog_domain_mappings.site_id;
			const env_name = this.dialog_domain_mappings.env_name.toLowerCase();

			axios.put(`/wp-json/captaincore/v1/sites/${site_id}/${env_name}/domains/primary`, {
				domain_id: domain.id,
				run_search_and_replace: true
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.snackbar.message = `Setting '${domain.name}' as primary domain. This may take a few minutes.`;
				this.snackbar.show = true;
				// API is async, so we wait a bit before refetching
				setTimeout(this.fetchDomainMappings, 5000);
			})
			.catch(error => {
				console.error("Error setting primary domain mapping:", error);
				this.dialog_domain_mappings.errors = ["Failed to set primary domain. " + (error.response?.data?.message || error.message)];
				this.dialog_domain_mappings.loading = false;
			});
		},
		openVerificationModal(domain) {
			console.log(domain)
			this.verificationDialog.domain = domain;
			this.verificationDialog.show = true;
		},
		attemptVerification() {
			this.verificationDialog.loading = true;
			const domain = this.verificationDialog.domain;
			const site_id = this.dialog_domain_mappings.site_id;

			const payload = {
				action: 'captaincore_ajax_check_verification',
				site_id: site_id,
				domain_id: domain.id,
				nonce: this.captaincore_nonce // Ensure you pass your nonce
			};

			// Example Axios call
			axios.post(ajaxurl, new URLSearchParams(payload))
			.then(response => {
				this.verificationDialog.loading = false;

				if (response.data.success) {
					const updatedDomain = response.data.data;

					// Update the domain in the main list
					const index = this.dialog_domain_mappings.domains.findIndex(d => d.id === updatedDomain.id);
					if (index !== -1) {
						// Use Vue.set or splice to ensure reactivity
						this.dialog_domain_mappings.domains[index] = updatedDomain;
					}

					if (updatedDomain.is_active) {
						this.verificationDialog.show = false;
					} else {
						alert("Kinsta is still waiting for DNS propagation. Please try again in a minute.");
					}
				} else {
					alert(response.data.data || "Verification check failed.");
				}
			})
			.catch(error => {
				this.verificationDialog.loading = false;
				console.error(error);
			});
		},
		showPushToOtherDialog() {
            const sourceSite = this.dialog_site.site;
            const sourceEnv = this.dialog_site.environment_selected;

            if (!sourceSite || !sourceEnv ) {
                this.snackbar.message = "This site does not have a provider configured.";
                this.snackbar.show = true;
                return;
            }

            this.dialog_push_to_other.source_site = sourceSite;
            this.dialog_push_to_other.source_env = sourceEnv;
            this.dialog_push_to_other.show = true;
            this.dialog_push_to_other.loading = true;
            this.dialog_push_to_other.targets = [];
            this.dialog_push_to_other.search = '';

            // MODIFIED: Call the new generic endpoint
            axios.get(`/wp-json/captaincore/v1/sites/${sourceSite.site_id}/environments/${sourceEnv.environment_id}/push-targets`, {
                headers: { 'X-WP-Nonce': this.wp_nonce }
            })
            .then(response => {
                this.dialog_push_to_other.targets = response.data;
                this.dialog_push_to_other.loading = false;
            })
            .catch(error => {
                console.error("Error fetching push targets:", error);
                this.snackbar.message = "Error fetching target sites: " + (error.response?.data?.message || error.message);
                this.snackbar.show = true;
                this.dialog_push_to_other.loading = false;
                this.dialog_push_to_other.show = false;
            });
        },
        isPushTargetSameAsSource(targetEnv) {
             return targetEnv.environment_id === this.dialog_push_to_other.source_env?.environment_id;
        },
        confirmPushToOther(target) {
            this.dialog_push_to_other.target_site = { name: target.site_name, site_id: target.site_id };
            this.dialog_push_to_other.target_env = { name: target.env_name, environment_id: target.environment_id, home_url: target.home_url };
            this.dialog_push_to_other.confirm.show = true;
        },
        executePushToOther() {
            this.dialog_push_to_other.confirm.show = false;
            this.dialog_push_to_other.show = false;

            const sourceEnv = this.dialog_push_to_other.source_env;
            const targetEnv = this.dialog_push_to_other.target_env;
			const sourceSite = this.dialog_push_to_other.source_site; // for job description
            const targetSite = this.dialog_push_to_other.target_site; // for job description

            if (!sourceEnv || !targetEnv) {
                this.snackbar.message = "Error: Missing source or target information.";
                this.snackbar.show = true;
                return;
            }

            const description = `Pushing ${sourceSite.name} (${sourceEnv.environment}) to ${targetSite.name} (${targetEnv.name})`;

            // Call the new generic endpoint with CaptainCore environment IDs
            axios.post('/wp-json/captaincore/v1/sites/environments/push', {
                source_environment_id: sourceEnv.environment_id,
                target_environment_id: targetEnv.environment_id
            }, {
                headers: { 'X-WP-Nonce': this.wp_nonce }
            })
            .then(response => {
                this.snackbar.message = "Push operation started.";
                this.snackbar.show = true;
                if (response.data.operation_id) {
                     this.checkProviderActions(); // Start checking provider actions
                }
            })
            .catch(error => {
                console.error("Error executing push:", error);
                this.snackbar.message = "Error starting push: " + (error.response?.data?.message || error.message);
                this.snackbar.show = true;
            });
        },
		sortEnvironments(environments) {
			if (!environments || !Array.isArray(environments)) return [];
			// Create a copy to prevent mutation warnings
			return [...environments].sort((a, b) => {
				// Production always comes first
				if (a.environment === 'Production') return -1;
				if (b.environment === 'Production') return 1;
				
				// Staging comes second
				if (a.environment === 'Staging') return -1;
				if (b.environment === 'Staging') return 1;
				
				// Otherwise sort alphabetically
				return a.environment.localeCompare(b.environment);
			});
		},
		getVisibleEnvironments(site) {
			if (!site.environments) return [];
			const matched = site.environments.filter(env => this.isEnvironmentMatched(env));
			return this.sortEnvironments(matched);
		},
		isEnvMatch(env) {
			if (!this.search && this.combinedAppliedFilters.length === 0 && this.applied_core_filters.length === 0) return true;
			
			let match = true;
			const searchLower = this.search ? this.search.toLowerCase() : '';

			// Search matches home_url or username
			if (searchLower) {
				match = (env.home_url && env.home_url.toLowerCase().includes(searchLower)) ||
						(env.username && env.username.toLowerCase().includes(searchLower));
			}

			// Core Filter
			if (match && this.applied_core_filters.length > 0) {
				match = this.applied_core_filters.some(filter => {
					return filter.name === env.core;
				});
			}

			// Advanced filters (Themes/Plugins)
			if (match && this.combinedAppliedFilters.length > 0) {
				match = this.combinedAppliedFilters.every(filter => {
					const list = filter.type === 'themes' ? env.themes : env.plugins;
					return list && list.some(item => item.name === filter.name);
				});
			}

			return match;
		},
		isEnvironmentMatched(env) {
			// 1. Text Search (Matches URL or Username)
			const searchLower = this.search ? this.search.toLowerCase() : '';
			let searchMatch = true;
			if (searchLower) {
				searchMatch = (env.home_url && env.home_url.toLowerCase().includes(searchLower)) ||
							(env.username && env.username.toLowerCase().includes(searchLower));
			}

			// 2. API Filter Logic (Themes/Plugins/Core)
			// If advanced filters are active, we check the list of IDs returned by the server
			let filterMatch = true;
			if (this.combinedAppliedFilters.length > 0 || this.applied_core_filters.length > 0) {
				
				// Prevent flash of 0 results while waiting for API response
				if (this.sites_loading) {
					return true;
				}

				// Cast IDs to strings/numbers consistently if needed, but usually types match from JSON
				filterMatch = this.filtered_environment_ids.some(id => id == env.environment_id);
			}

			return searchMatch && filterMatch;
		},
		selectAllMatchesToTerminal() {
			const newTargets = [];
			// Create a Set of currently selected IDs for efficient lookup (normalize to string)
			const currentIds = new Set(this.view_console.selected_targets.map(t => String(t.environment_id)));
			
			// Iterate through currently visible sites (filteredSites)
			this.filteredSites.forEach(site => {
				// Iterate through that site's environments
				if (site.environments) {
					site.environments.forEach(env => {
						// Check if the specific environment matches current filters
						if (this.isEnvironmentMatched(env)) {
							const envIdStr = String(env.environment_id);
							// Only add if not already selected
							if (!currentIds.has(envIdStr)) {
								newTargets.push({
									site_id: site.site_id,
									name: site.name,
									environment_id: env.environment_id,
									environment: env.environment,
									home_url: env.home_url
								});
								// Add to set to prevent duplicates if data has issues
								currentIds.add(envIdStr);
							}
						}
					});
				}
			});

			if (newTargets.length === 0) {
				if (this.filteredEnvironmentsCount > 0) {
					this.snackbar.message = "All filtered environments are already selected.";
				} else {
					this.snackbar.message = "No environments match current filters.";
				}
				this.snackbar.show = true;
				return;
			}

			// Append only new, unique targets
			this.view_console.selected_targets.push(...newTargets);
			
			this.view_console.terminal_open = true;
			this.view_console.show = true;
			this.snackbar.message = `${newTargets.length} environments added to selection.`;
			this.snackbar.show = true;
		},
		openTerminalForCurrentEnv( focusInput = true ) {
			const site = this.dialog_site.site;
			const env = this.dialog_site.environment_selected;
			
			// 1. Set the target
			this.view_console.selected_targets = [{
				site_id: site.site_id,
				name: site.name,
				environment_id: env.environment_id,
				environment: env.environment,
				home_url: env.home_url
			}];

			// 2. Open the UI
			this.view_console.terminal_open = true;
			this.view_console.show = true;

			// 3. Focus input
			if (focusInput) {
				this.$nextTick(() => {
					if (this.$refs.terminalInput) {
						this.$refs.terminalInput.focus();
					}
				});
			}
		},
		toggleConsoleTarget(env) {
			const index = this.view_console.selected_targets.findIndex(t => t.environment_id == env.environment_id);
			if (index > -1) {
				this.view_console.selected_targets.splice(index, 1);
			} else {
				// Normalize the object structure to match what the autocomplete uses
				this.view_console.selected_targets.push({
					site_id: env.site_id,
					name: env.name,
					environment_id: env.environment_id,
					environment: env.environment,
					home_url: env.home_url
				});
			}
		},
		onConsoleTargetIntersect(isIntersecting, entries, observer) {
			if (isIntersecting) {
				// Load the next batch
				this.view_console.target_limit += 20;
			}
		},
		runRecipeFromUI(recipe) {
			this.openTerminalForCurrentEnv(false);
			// Pre-fill the code
			this.script.code = recipe.content;
			// Execute immediately? Or let user review? 
			// Let's preview it in the input for safety as per terminal pattern
			this.$nextTick(() => {
				if (this.$refs.terminalInput) {
					this.$refs.terminalInput.focus();
				}
			});
		},
		runSystemTool(methodName) {
			if (this.view_console.selected_targets.length === 0) {
				this.view_console.target_menu = true;
				this.snackbar.message = "Please select at least one target environment first.";
				this.snackbar.show = true;
				return;
			}

			// Map Terminal targets to the format existing bulk methods expect (sites_selected)
			// We temporarily sync the selections so the existing dialogs work.
			this.sites_selected = this.view_console.selected_targets.map(t => {
				const originalSite = this.sites.find(s => s.site_id == t.site_id);
				if (!originalSite) return null;
				
				// Create a shallow copy to attach context without polluting the global store
				const siteContext = Object.assign({}, originalSite);
				
				// Attach the specific environment selection from the terminal target
				// This allows getEnvironmentIdsFromSelection to prioritize this ID
				siteContext.environment_selected = {
					environment_id: t.environment_id,
					environment: t.environment
				};
				
				return siteContext;
			}).filter(Boolean);

			// Set the bulk environment to match the terminal's context
			// Note: This logic assumes all selected targets share the same env (Prod/Staging) 
			// or defaults to the first selected one.
			this.dialog_bulk_tools.environment_selected = this.view_console.selected_targets[0].environment;

			// Call the existing method
			this[methodName]();
		},
		runScript( site_id, environment_id = null ) {
			let env;
			
			if ( environment_id ) {
				// Find specific environment passed from UI
				env = this.environments.find(e => e.environment_id == environment_id);
			} else {
				// Fallback to Production if not specified
				env = this.environments.find(e => e.environment === 'Production' && e.site_id == site_id );
			}

			if ( !env ) {
				this.view_console.terminal_open = true;
				return
			}
			this.view_console.selected_targets = [ env ];

			// Open terminal and sidebar
			this.view_console.terminal_open = true;

			// Focus the input field
			this.$nextTick(() => {
				if (this.$refs.terminalInput) {
					this.$refs.terminalInput.focus();
				}
			});
		},
		openQuickRun(site) {
			// Set the global site context
			this.dialog_site.site = site;

			// Auto-select the production environment of the clicked site in the terminal
			if (site.environments && site.environments.length > 0) {
				// Find production or default to the first available environment
				const env = site.environments.find(e => e.environment === 'Production') || site.environments[0];
				this.view_console.selected_targets = [{
					unique_id: `${site.site_id}_${env.environment}`,
					site_id: site.site_id,
					site_name: site.name,
					environment_id: env.environment_id,
					environment: env.environment,
					home_url: env.home_url
				}];
			}

			// Open terminal and sidebar
			this.view_console.terminal_open = true;

			// Focus the input field for immediate typing
			this.$nextTick(() => {
				if (this.$refs.terminalInput) {
					this.$refs.terminalInput.focus();
				}
			});
		},
		scrollToTerminalBottom() {
			this.$nextTick(() => {
				const terminal = this.$refs.terminalBody;
				const element = terminal.$el || terminal; 
				if (element) {
					element.scrollTop = element.scrollHeight;
				}
			});
		},
		toggleFullscreen() {
			this.view_console.fullscreen = !this.view_console.fullscreen;
		},

		// 2. Execute from bottom input
		executeTerminalCommand() {
			if (!this.script.code || this.script.code.trim() === "") return;

			const targets = this.view_console.selected_targets;

			if (!targets || targets.length === 0) {
				this.view_console.target_menu = true;
				this.snackbar.message = "Please select at least one target environment.";
				this.snackbar.show = true;
				return;
			}
			
			const targetPayload = targets.map(t => {
				// If we have an explicit environment_id, prefer that
				if (t.environment_id) return { enviroment_id: t.environment_id }; // Note typo 'enviroment_id' in legacy API if applicable
				
				// Fallback or just passing metadata if your API handles it differently
				return { site_id: t.site_id, environment: t.environment };
			});

			const description = targets.length === 1 
				? `Running command on ${targets[0].home_url}`
				: `Running command on ${targets.length} environments`;

			// 1. Clear Input
			const cmd = this.script.code;
			this.script.code = "";

			// 2. Start Job Entry
			const job_id = Math.round((new Date()).getTime());
			this.jobs.push({ 
				"job_id": job_id, 
				"description": description, 
				"status": "queued", 
				"stream": [],
				"created_at": new Date()
			});

			// 3. Call Bulk Endpoint
			// This reuses the logic from 'runCustomCodeBulkEnvironments'
			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: targetPayload.map(t => t.enviroment_id || t), // Adjust based on your exact API needs
				code: cmd
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				// Attach WebSocket ID
				const job = this.jobs.find(j => j.job_id === job_id);
				if (job) {
					job.job_id = response.data; // Server returns the actual job token
					this.runCommand(response.data);
				}
				this.scrollToTerminalBottom();
			})
			.catch(error => {
				const job = this.jobs.find(j => j.job_id === job_id);
				if (job) {
					job.status = "error";
					job.stream.push("Error starting command: " + (error.response?.data?.message || error.message));
				}
			});

			// 4. Focus back
			this.$nextTick(() => {
				if(this.$refs.terminalInput) this.$refs.terminalInput.focus();
			});
		},

		// 3. Populate input when clicking a recipe (instead of auto-running)
		previewRecipeInInput(recipe) {
			this.script.code = recipe.content;
			this.$nextTick(() => {
				this.$refs.terminalInput.focus();
			});
		},

		// 4. Handle switching sites from the terminal sidebar
		switchTerminalSite(newSite) {
			// Update the global context used by runCustomCode
			this.dialog_site.site = newSite;
			// Attempt to set environment (default to Production)
			if (newSite.environments && newSite.environments.length > 0) {
				this.dialog_site.environment_selected = newSite.environments[0];
			} else {
				// Fallback if detailed data isn't loaded
				this.dialog_site.environment_selected = { environment: "Production", home_url: newSite.name };
			}
		},
		openProductionSite(site) {
			let url = site.name; // Default fallback
			
			// Try to find Production environment URL
			if (site.environments) {
				const prod = site.environments.find(e => e.environment === 'Production');
				if (prod && prod.home_url) {
					url = prod.home_url;
				}
			}
			
			// Ensure protocol exists
			if (!url.startsWith('http')) {
				url = 'https://' + url;
			}
			
			window.open(url, '_blank');
		},
		openEnvironmentTool(site, env, slug) {
			// 1. Tell the dialog which environment ID we want to load
			this.dialog_site.desired_environment_id = env.environment_id;

			// 2. Construct path. If slug is empty, go to main info page
			const path = slug ? `/sites/${site.site_id}/${slug}` : `/sites/${site.site_id}`;

			// 3. Navigate
			this.goToPath(path);
		},
		onTerminalDateChange(newDate) {
			this.terminal_schedule.date = dayjs(newDate).format('YYYY-MM-DD');
			this.terminal_schedule.menu_date = false;
		},
		openSaveAsRecipe() {
			if (!this.script.code || this.script.code.trim() === "") return;

			// reset recipe object
			this.new_recipe.title = ""; 
			this.new_recipe.public = 1;

			// Pre-fill content with terminal input
			this.new_recipe.content = this.script.code; 

			// Open the existing New Recipe dialog
			this.new_recipe.show = true;
		},
		openTerminalSchedule() {
			if (!this.script.code || this.script.code.trim() === "") return;
			
			if (this.view_console.selected_targets.length === 0) {
				this.view_console.target_menu = true;
				this.snackbar.message = "Please select at least one target environment.";
				this.snackbar.show = true;
				return;
			}

			// Set default time to tomorrow 5am or next hour
			const tomorrow = dayjs().add(1, 'day');
			this.terminal_schedule.date = tomorrow.format('YYYY-MM-DD');
			this.terminal_schedule.date_obj = new Date(tomorrow); // For v-date-picker model
			this.terminal_schedule.time = "05:00";
			this.terminal_schedule.show = true;
		},
		confirmTerminalSchedule() {
			this.terminal_schedule.loading = true;
			const targets = this.view_console.selected_targets;
			const code = this.script.code;
			const promises = [];

			// Determine environment IDs. Handle both legacy object format and new format if mixed.
			// Similar logic to executeTerminalCommand payload preparation.
			targets.forEach(target => {
				const envId = target.environment_id || target.enviroment_id; // Handle typo if present in data
				
				if (envId) {
					const payload = {
						environment_id: envId,
						code: code,
						run_at: {
							time: this.terminal_schedule.time,
							date: this.terminal_schedule.date,
							timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
						}
					};

					const req = axios.post(`/wp-json/captaincore/v1/scripts/schedule`, payload, {
						headers: { 'X-WP-Nonce': this.wp_nonce }
					});
					promises.push(req);
				}
			});

			Promise.all(promises)
				.then(() => {
					this.snackbar.message = `Script scheduled for ${targets.length} environment(s).`;
					this.snackbar.show = true;
					this.terminal_schedule.show = false;
					this.script.code = ""; // Clear input after scheduling
					
					// If we are currently viewing the environment in the background, refresh scripts list
					if (this.dialog_site.environment_selected && this.dialog_site.site.site_id) {
						this.fetchSiteEnvironments(this.dialog_site.site.site_id);
					}
				})
				.catch(error => {
					console.error("Error scheduling scripts:", error);
					this.snackbar.message = "Error scheduling scripts. Check console.";
					this.snackbar.show = true;
				})
				.finally(() => {
					this.terminal_schedule.loading = false;
				});
		},
		magicLoginSite(site_id, user, environment_obj) {
			// 1. Fallback: If environment_obj isn't passed, use the one currently selected in the dialog
			const targetEnv = environment_obj || this.dialog_site.environment_selected;

			// 2. Guard: If we still don't have an environment, default to 'production' or exit
			let environment_name = typeof targetEnv === 'object' ? targetEnv.environment : targetEnv;
			if (!environment_name) environment_name = 'production'; 
			
			let env_key = environment_name.toLowerCase();

			// 3. Set loading state on the specific user (item) AND the environment object
			if (user && typeof user === 'object') user.isLoggingIn = true;
			if (targetEnv && typeof targetEnv === 'object') targetEnv.isLoggingIn = true;

			let endpoint = `/wp-json/captaincore/v1/sites/${site_id}/${env_key}/magiclogin`;
			if (user && user.ID) {
				endpoint = `/wp-json/captaincore/v1/sites/${site_id}/${env_key}/magiclogin/${user.ID}`;
			}

			axios.get(endpoint, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				if (response.data.includes("There has been a critical error on this website")) {
					this.snackbar.message = "Login failed due to PHP error. Check server PHP logs.";
					this.snackbar.show = true;
					return;
				}
				if (response.data.includes("http")) {
					window.open(response.data.trim());
				} else {
					this.snackbar.message = "Login failed.";
					this.snackbar.show = true;
				}
			})
			.catch(error => {
				this.snackbar.message = "Login request failed.";
				this.snackbar.show = true;
			})
			.finally(() => {
				// 4. Reset loading states
				if (user && typeof user === 'object') user.isLoggingIn = false;
				if (targetEnv && typeof targetEnv === 'object') targetEnv.isLoggingIn = false;
			});
		},
		magicLoginForDeployTarget(target) {
			let job_id = Math.round((new Date()).getTime());
			let environment = target.environment.toLowerCase();
			let description = `Magic login to ${target.home_url}`;
			let endpoint = `/wp-json/captaincore/v1/sites/${target.id}/${environment}/magiclogin`;
			
			this.jobs.push({"job_id": job_id,"description": description, "status": "running", "command":"login"});

			axios.get( endpoint, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					if ( response.data.includes("There has been a critical error on this website") ) {
						this.jobs.filter(job => job.job_id == job_id)[0].status = "error";
						this.snackbar.message = description + " failed due to PHP error. Check server PHP logs.";
						this.snackbar.show = true;
						return
					}
					if ( response.data.includes("http") ) {
						window.open( response.data.trim() );
						this.jobs.filter(job => job.job_id == job_id)[0].status = "done";
					} else {
						this.jobs.filter(job => job.job_id == job_id)[0].status = "error";
						this.snackbar.message = description + " failed.";
						this.snackbar.show = true;
					}
				})
				.catch(error => {
					this.jobs.filter(job => job.job_id == job_id)[0].status = "error";
					this.snackbar.message = description + " failed.";
					this.snackbar.show = true;
					console.log(error.response)
				});
		},
		isValidEmail(email) {
			return /.+@.+\..+/.test(email);
		},
		openShareDialog(site) {
			this.dialog_share.show = true;
			this.dialog_share.loading = true;
			this.dialog_share.site_id = site.site_id;
			this.dialog_share.email = "";
			this.dialog_share.error = "";
			
			// Fetch preview data
			axios.get(`/wp-json/captaincore/v1/sites/${site.site_id}/invite-preview`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.dialog_share.preview = response.data;
				this.dialog_share.loading = false;
			})
			.catch(error => {
				console.error(error);
				this.dialog_share.loading = false;
				this.snackbar.message = "Error loading share details.";
				this.snackbar.show = true;
				this.dialog_share.show = false;
			});
		},
		sendSiteInvite() {
			this.dialog_share.sending = true;
			this.dialog_share.error = "";

			axios.post(`/wp-json/captaincore/v1/sites/${this.dialog_share.site_id}/invite`, {
				email: this.dialog_share.email
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.dialog_share.sending = false;
				this.dialog_share.show = false;
				this.snackbar.message = response.data.message || "Invitation sent successfully.";
				this.snackbar.show = true;
			})
			.catch(error => {
				this.dialog_share.sending = false;
				const msg = error.response && error.response.data && error.response.data.message 
					? error.response.data.message 
					: "Error sending invite.";
				this.dialog_share.error = msg;
			});
		},
		inputFile (newFile, oldFile) {

			if (newFile && oldFile) {
				// Uploaded successfully
				if (newFile.success && !oldFile.success) {
					new_response = JSON.parse( newFile.response );
					if ( new_response.response == "Error" ) {

						if ( this.new_theme.show ) {
							this.new_theme.show = false;
							this.snackbar.message = "Installing theme failed.";
							this.snackbar.show = true;
							description = "Installing theme '" + newFile.name + "' to " + this.new_theme.site_name;

							// Adds new job
							job_id = Math.round((new Date()).getTime());
							this.jobs.push({"job_id": job_id,"description": description, "status": "error", stream: []});
						}

						if ( this.new_plugin.show ) {
							this.new_plugin.show = false;
							this.snackbar.message = "Installing plugin failed.";
							this.snackbar.show = true;
							description = "Installing plugin '" + newFile.name + "' to " + this.new_plugin.site_name;
							
							// Adds new job
							job_id = Math.round((new Date()).getTime());
							this.jobs.push({"job_id": job_id,"description": description, "status": "error", stream: []});
						}

					}
					if ( new_response.response == "Success" && new_response.url ) {

						if ( this.new_plugin.show ) {
							this.new_plugin.show = false;

							this.upload = [];

							// run wp cli with new plugin url and site
							site_ids = this.new_plugin.sites.map( s => s.site_id );

							// Adds new job
							job_id = Math.round((new Date()).getTime());
							description = "Installing plugin '" + newFile.name + "' to " + this.new_plugin.site_name;
							this.jobs.push({"job_id": job_id, "site_id": site_ids, "description": description, "status": "queued", stream: [], "command": "manage"});

							// Builds WP-CLI
							wp_cli = `wp plugin install --skip-plugins --skip-themes --force --activate '${new_response.url}'`

							// Prep AJAX request
							var data = {
								'action': 'captaincore_install',
								'post_id': site_ids,
								'command': "manage",
								'value': "ssh",
								'background': true,
								'environment': this.dialog_site.environment_selected.environment,
								'arguments': { "name":"Commands","value":"command","command":"ssh","input": wp_cli }
							};

							// Housecleaning
							this.new_plugin.sites = [];
							this.new_plugin.site_name = "";
							this.new_plugin.environment_selected = "";
						}
						if ( this.new_theme.show ) {
							this.new_theme.show = false;
							this.upload = [];

							// run wp cli with new plugin url and site
							site_ids = this.new_theme.sites.map( s => s.site_id );

							// Adds new job
							job_id = Math.round((new Date()).getTime());
							description = "Installing theme '" + newFile.name + "' to " + this.new_theme.site_name;
							this.jobs.push({"job_id": job_id, "site_id": site_ids, "description": description, "status": "queued", stream: [], "command": "manage"});

							// Builds WP-CLI
							wp_cli = "wp theme install '" + new_response.url + "' --force"

							// Prep AJAX request
							var data = {
								'action': 'captaincore_install',
								'post_id': site_ids,
								'command': "manage",
								'value': "ssh",
								'background': true,
								'environment': this.dialog_site.environment_selected.environment,
								'arguments': { "name":"Commands","value":"command","command":"ssh","input": wp_cli }
							};

							// Housecleaning
							this.new_theme.sites = [];
							this.new_theme.site_name = "";
							this.new_theme.environment_selected = "";
						}

						self = this;
						axios.post( ajaxurl, Qs.stringify( data ) )
							.then( response => {
								self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
								self.runCommand( response.data );
							})
							.catch(error => {
								console.log( error.response )
							});
					}
				}
			}

			// Automatically activate upload
			if (Boolean(newFile) !== Boolean(oldFile) || oldFile.error !== newFile.error) {
				if (!this.$refs.upload.active) {
					this.$refs.upload.active = true;
				}
			}
		},
		preloadStagingEnvironment( targetObject ) {
			// Copy production address to staging field
			targetObject.environments[1].address = targetObject.environments[0].address;
			
			if ( targetObject.environments[0].address.includes(".kinsta.cloud") ) {
				targetObject.environments[1].address = "staging-" + targetObject.environments[0].address
			}

			if ( targetObject.provider == "kinsta" ) {
				// Copy production username to staging field
				targetObject.environments[1].username = targetObject.environments[0].username;
				// Copy production password to staging field (If Kinsta address)
				targetObject.environments[1].password = targetObject.environments[0].password;
			} else {
				// Copy production username to staging field with staging suffix
				targetObject.environments[1].username = targetObject.environments[0].username + "-staging";
			}

			// Copy remaining fields
			targetObject.environments[1].port = targetObject.environments[0].port;
			targetObject.environments[1].protocol = targetObject.environments[0].protocol;
			targetObject.environments[1].home_directory = targetObject.environments[0].home_directory;
		},
		checkRequestedSites() {
			var data = {
				'action': 'captaincore_user',
				'command': "fetchRequestedSites",
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.requested_sites = response.data
					if ( this.requested_sites.length != 0 ) {
						setTimeout(this.checkRequestedSites, 5000)
					}
					if ( this.requested_sites.length == 0 && this.role == 'administrator' ) {
						setTimeout(this.checkRequestedSites, 5000)
					}
				})
				.catch( error => console.log( error ) );
		},
		verifyKinstaConnection() {
			axios.get( '/wp-json/captaincore/v1/providers/kinsta/verify', {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.dialog_new_site_kinsta.connection_verified = response.data
				this.dialog_new_site_kinsta.verifing = false
			});
		},
		showNewSiteKinsta() {
			this.dialog_new_site_kinsta.verifing = false
			this.dialog_new_site_kinsta.connection_verified = true
			this.dialog_new_site_kinsta.show = true
			if ( this.role != 'administrator' ) {
				this.dialog_new_site_kinsta.site.account_id = this.accounts[0].account_id
			}
			if ( this.role == 'administrator' ) {
				this.dialog_new_site_kinsta.verifing = true
				this.dialog_new_site_kinsta.connection_verified = false
				this.verifyKinstaConnection()
			}
		},
		connectKinsta() {
			this.dialog_new_site_kinsta.verifing = true
			axios.post( '/wp-json/captaincore/v1/providers/kinsta/connect', {
				token: this.dialog_new_site_kinsta.kinsta_token
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.dialog_new_site_kinsta.connection_verified = response.data
				this.dialog_new_site_kinsta.verifing = false
			});
		},
		fetchProviderActions() {
			axios.get( '/wp-json/captaincore/v1/provider-actions', {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			}).then( response => {
				this.provider_actions = response.data
				if ( this.provider_actions.length > 0 ) {
					setTimeout(this.checkProviderActions, 10000)
				}
			})
		},
		checkProviderActions() {
			axios.get( '/wp-json/captaincore/v1/provider-actions/check', {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			}).then( response => {
				this.provider_actions = response.data
				if ( this.provider_actions.length > 0 ) {
					setTimeout(this.checkProviderActions, 10000)
				}
				this.runProviderActions()
			})
		},
		runProviderActions() {
			this.provider_actions.forEach( action => {
				if ( action.status == "waiting" ) {
					site = action.action
					axios.get( `/wp-json/captaincore/v1/provider-actions/${action.provider_action_id}/run`, {
						headers: { 'X-WP-Nonce':this.wp_nonce }
					}).then( response => {
						if ( site.command == 'new-site' ) {
							this.snackbar.message = `New site ${site.name} created at Kinsta's datacenter ${site.datacenter}.`
							this.snackbar.show = true
						}
						if ( site.command == 'deploy-to-staging' && site.step == "2" ) {
							this.snackbar.message = `Deployed ${site.name} to staging site.`
							this.snackbar.show = true
							if ( this.dialog_site.site.site_id == site.site_id ) {
								this.syncSiteEnvironment( site.site_id, "staging" )
								this.fetchSiteInfo( site.site_id )
							}
						}
						if ( site.command == 'deploy-to-production' ) {
							this.snackbar.message = `Deployed ${site.name} to production site.`
							this.snackbar.show = true
							if ( this.dialog_site.site.site_id == site.site_id ) {
								this.syncSiteEnvironment( site.site_id, "production" )
								this.fetchSiteInfo( site.site_id )
							}
						}
						this.fetchAccounts()
						this.provider_actions = response.data
						this.fetchSites();
					})
				}
			});
		},
		newKinstaSite() {
			axios.post( '/wp-json/captaincore/v1/providers/kinsta/new-site', {
				site: this.dialog_new_site_kinsta.site
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				if ( response.data.errors ) {
					this.dialog_new_site_kinsta.errors = response.data.errors
					return
				}
				this.snackbar.message = `Site ${this.dialog_new_site_kinsta.site.name} is being created at Kinsta. Will notify once completed.`
				this.snackbar.show = true
				provider_id = this.dialog_new_site_kinsta.site.provider_id
				this.dialog_new_site_kinsta = { show: false, errors: [], working: false, verifing: true, connection_verified: false, kinsta_token: "", site: { name: "", domain: "", clone_site_id: "", provider_id: provider_id, datacenter: "us-ashburn-1", domains: [], shared_with: [], account_id: "", customer_id: "" } }
				this.checkProviderActions()
			});
		},
		requestSite() {
			if ( this.dialog_request_site.request.name == "" || this.dialog_request_site.request.account_id == "" ) {
				this.snackbar.message = "Please enter a site name."
				this.snackbar.show = true
				return
			}
			this.dialog_request_site.request.created_at = Math.round((new Date()).getTime() / 1000)
			this.dialog_request_site.request.step = 1
			var data = {
				'action': 'captaincore_account',
				'command': "requestSite",
				'value': this.dialog_request_site.request,
				'account_id': this.dialog_request_site.request.account_id
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.requested_sites = response.data
					if ( this.requested_sites.length == 1 ) {
						this.checkRequestedSites()
					}
				})
				.catch( error => console.log( error ) );
			name = this.dialog_request_site.request.name
			this.snackbar.message = `Requesting new site for ${name}`
			this.snackbar.show = true
			this.dialog_request_site = { show: false, request: { name: "", account_id: "", notes: "" } }
		},
		backRequestSite( site_request ) {
			var data = {
				'action': 'captaincore_account',
				'command': "backRequestSite",
				'value': site_request,
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.requested_sites = response.data
				})
				.catch( error => console.log( error ) )
		},
		continueRequestSite( site_request ) {
			var data = {
				'action': 'captaincore_account',
				'command': "continueRequestSite",
				'value': site_request,
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.requested_sites = response.data
				})
				.catch( error => console.log( error ) )
		},
		updateRequestSite() {
			var data = {
				'action': 'captaincore_account',
				'command': "updateRequestSite",
				'value': this.dialog_site_request.request,
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_site_request.show = false
					this.requested_sites = response.data
				})
				.catch( error => console.log( error ) )
		},
		modifyRequest( index ) {
			this.dialog_site_request.show = true
			this.dialog_site_request.request = JSON.parse ( JSON.stringify ( this.requested_sites[index] ) )
		},
		finishRequest( index ) {
			site_request = this.requested_sites[index]
			var data = {
				'action': 'captaincore_account',
				'command': "deleteRequestSite",
				'value': site_request,
				'account_id': site_request.account_id
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.requested_sites = response.data
				})
				.catch( error => console.log( error ) )
		},
		cancelRequest( index ) {
			site_request = this.requested_sites[index]
			should_proceed = confirm( `Cancel request to create site "${site_request.name}" for account "${this.account_name( site_request.account_id )}".` )
			if ( ! should_proceed ) {
				return
			}
			var data = {
				'action': 'captaincore_account',
				'command': "deleteRequestSite",
				'value': site_request,
				'account_id': site_request.account_id
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.requested_sites = response.data
				})
				.catch( error => console.log( error ) )
		},
		submitNewSite() {
			this.dialog_new_site.saving = true
			new_site = this.dialog_new_site
			new_site.shared_with = new_site.shared_with.map( a => a.account_id )
			site_name = this.dialog_new_site.name;

			axios.post(
				`/wp-json/captaincore/v1/sites`, {
					site: this.dialog_new_site
				}, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then( response => {
					// Read JSON response
					var response = response.data;

					// If error then response
					if ( response.errors.length > 0 ) {
						this.dialog_new_site.saving = false
						this.dialog_new_site.errors = response.errors
						return;
					}

					if ( response.response = "Successfully added new site" ) {
						this.fetchSiteInfo( response.site_id )
						this.goToPath( `/sites/${response.site_id}` )
						// Fetch updated accounts
						axios.get(
							'/wp-json/captaincore/v1/accounts', {
								headers: {'X-WP-Nonce':this.wp_nonce}
							})
							.then( response => {
								this.accounts = response.data
							});
						
						// Start job
						description = "Adding " + site_name;
						job_id = Math.round((new Date()).getTime());
						this.jobs.push({"job_id": job_id,"description": description, "status": "running", stream: []});

						// Run prep immediately after site added.
						var data = {
							'action': 'captaincore_install',
							'command': "new",
							'post_id': response.site_id
						};
						axios.post( ajaxurl, Qs.stringify( data ) )
							.then( r => {
								this.jobs.filter(job => job.job_id == job_id)[0].job_id = r.data
								this.runCommand( r.data )
							})
					}
				});
		},
		updateSite() {
			this.dialog_edit_site.loading = true;
			site_update = JSON.parse ( JSON.stringify ( this.dialog_edit_site.site ) )
			site_update.shared_with = site_update.shared_with.map( a => a.account_id )
			site_name = site_update.name
			site_id   = site_update.site_id
			var data = {
				'action': 'captaincore_ajax',
				'command': "updateSite",
				'value': site_update
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					var response = response.data

					// If error then response
					if ( response.response.includes("Error:") ) {
						this.dialog_edit_site.errors = [ response.response ];
						console.log( response.response );
						return;
					}

					if ( response.response = "Successfully updated site" ) {
						this.fetchSiteEnvironments( site_id )
						this.fetchSiteDetails( site_id )
						this.dialog_site.step = 2
						this.dialog_edit_site = { show: false, loading: false, site: {} }

						// Start job
						description = "Updating " + site_name;
						job_id = Math.round((new Date()).getTime());
						this.jobs.push({"job_id": job_id,"description": description, "status": "running", stream: []});

						// Run prep immediately after site added.
						var data = {
							'action': 'captaincore_install',
							'command': "update",
							'post_id': response.site_id
						};
						axios.post( ajaxurl, Qs.stringify( data ) )
							.then( r => {
								this.jobs.filter(job => job.job_id == job_id)[0].job_id = r.data
								this.runCommand( r.data )
							});
					}
				});
		},
		checkSSH( site ) {
			site.loading = true
			var data = {
				action: 'captaincore_install',
				post_id: site.site_id,
				command: 'sync-data',
				environment: this.dialog_site.environment_selected.environment
			};

			description = "Checking " + site.name + " SSH connection";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({ "job_id": job_id, "description": description, "status": "queued", stream: [], "command": "checkSSH", "site_id": site.site_id });

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with responsed background job id
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					this.runCommand( response.data );
				})
				.catch( error => console.log( error ) );
		},
		syncSite() {
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected.environment.toLowerCase()
			description = "Syncing " + this.dialog_site.environment_selected.home_url + " info";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({ "job_id": job_id, "description": description, "status": "queued", stream: [], "command": "syncSite", "site_id": site.site_id });

			axios.get(
				`/wp-json/captaincore/v1/sites/${site.site_id}/${environment}/sync/data`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data
					this.runCommand( response.data )
				})

		},
		syncSiteEnvironment( site_id, environment ) {
			axios.get( `/wp-json/captaincore/v1/sites/${site_id}/environments`, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				environments = response.data
				environment_selected = environments.filter( e => e.environment.toLowerCase() == environment.toLowerCase() )[0]
			
				description = "Syncing " + environment_selected.home_url + " info";

				// Start job
				job_id = Math.round((new Date()).getTime());
				this.jobs.push({ "job_id": job_id, "description": description, "status": "queued", stream: [], "command": "syncSite", "site_id": site_id });

				axios.get(
					`/wp-json/captaincore/v1/sites/${site_id}/${environment}/sync/data`, {
						headers: {'X-WP-Nonce':this.wp_nonce}
					})
					.then(response => {
						this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data
						this.runCommand( response.data )
					})
			});
		},
		executeBulkTool(toolName, extraParams = {}, description = "") {
			const env_ids = this.view_console.selected_targets.map(t => t.environment_id);
			
			if (env_ids.length === 0) {
				this.view_console.target_menu = true;
				this.snackbar.message = "Please select target environments.";
				this.snackbar.show = true;
				return;
			}

			if (!description) {
				description = `Running ${toolName} on ${env_ids.length} environments`;
			}

			const job_id = Math.round((new Date()).getTime());
			this.jobs.push({ "job_id": job_id, "description": description, "status": "queued", "stream": [] });

			axios.post('/wp-json/captaincore/v1/sites/bulk-tools', {
				tool: toolName,
				environments: env_ids,
				params: extraParams
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				const job = this.jobs.find(j => j.job_id === job_id);
				if (job) {
					job.job_id = response.data;
					this.runCommand(response.data);
				}
			})
			.catch(error => {
				const job = this.jobs.find(j => j.job_id === job_id);
				if (job) job.status = "error";
				this.snackbar.message = "Operation failed: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
			});
		},
		bulkSyncSites() {
			this.executeBulkTool('sync-data', {}, "Syncing data for environments");
		},
		getEnvironmentIdsFromSelection(sites, defaultEnvName = "Production") {
			return sites.map(site => {
				// 1. Check if the site object has a specifically assigned environment object
				if (site.current_env && site.current_env.environment_id) {
					return site.current_env.environment_id;
				}

				// 2. Check if the site object has a selected environment
				if (site.environment_selected && site.environment_selected.environment_id) {
					return site.environment_selected.environment_id;
				}

				// 3. Fallback: Search the environments array for the default name
				if (site.environments && Array.isArray(site.environments)) {
					const match = site.environments.find(e => e.environment.toLowerCase() === defaultEnvName.toLowerCase());
					if (match) return match.environment_id;
				}
				
				return null;
			}).filter(id => id !== null);
		},
		fetchVulnerabilityScans() {
			axios.get( `/wp-json/captaincore/v1/sites/vulnerability-scans`, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.vulnerabilities = response.data
			});
		},
		openEnvironmentTool(site, env, slug) {
			// 1. Tell the dialog which environment ID we want to load once data is fetched
			this.dialog_site.desired_environment_id = env.environment_id;

			// 2. Construct path (e.g. /sites/123/updates)
			const path = `/sites/${site.site_id}/${slug}`;

			// 3. Navigate
			this.goToPath(path);
		},
		fetchSiteEnvironments( site_id ) {
			axios.get( `/wp-json/captaincore/v1/sites/${site_id}/environments`, {
				headers: {'X-WP-Nonce':this.wp_nonce}
			})
			.then( response => {
				this.dialog_site.site.environments = response.data
				this.dialog_site.site.environments.forEach( e => {
					e.environment_label = e.environment + " Environment"
				})

				// Check if we requested a specific environment via the UI
				if ( this.dialog_site.desired_environment_id ) {
					const desiredEnv = this.dialog_site.site.environments.find( e => e.environment_id == this.dialog_site.desired_environment_id );
					if ( desiredEnv ) {
						this.dialog_site.environment_selected = desiredEnv;
					} else {
						this.dialog_site.environment_selected = this.dialog_site.site.environments[0];
					}
					// Reset the flag
					this.dialog_site.desired_environment_id = null;
				} 
				// Logic for handling "Add Site" step 2 (existing logic)
				else if ( this.dialog_site.step == 2 && typeof this.dialog_site.environment_selected != 'undefined' && this.dialog_site.environment_selected.environment ) {
					const matchingEnv = this.dialog_site.site.environments.find(e => e.environment === this.dialog_site.environment_selected.environment);
					if (matchingEnv) {
						this.dialog_site.environment_selected = matchingEnv;
					} else {
						this.dialog_site.environment_selected = this.dialog_site.site.environments[0] || {};
					}
				} else {
					this.dialog_site.environment_selected = this.dialog_site.site.environments[0]
				}
				this.dialog_site.loading = false
				if ( this.dialog_site.site.tabs_management == "tab-Users" ) {
					this.fetchUsers()
				}
				if ( this.dialog_site.site.tabs_management == "tab-Stats" ) {
					this.fetchStats()
				}
				if ( this.dialog_site.site.tabs_management == "tab-Updates" ) {
					this.viewUpdateLogs( this.dialog_site.site.site_id )
				}
				if ( this.dialog_site.site.tabs_management == "tab-Backups" ) {
					this.viewQuicksaves()
					this.viewSnapshots()
					this.dialog_site.backup_step = 1
				}
				if ( this.dialog_site.site.tabs_management == "tab-Backups" && this.route_path.endsWith( "/backups" ) ) {
					this.viewBackups()
					this.dialog_site.backup_step = 2
				}
				if ( this.dialog_site.site.tabs_management == "tab-Backups" && this.route_path.endsWith( "/quicksaves" ) ) {
					this.viewQuicksaves()
					this.dialog_site.backup_step = 3
				}
				if ( this.dialog_site.site.tabs_management == "tab-Backups" && this.route_path.endsWith( "/snapshots" ) ) {
					this.viewSnapshots()
					this.dialog_site.backup_step = 4
				}
				if ( this.dialog_site.site.tabs_management == "tab-Info" && this.route_path.endsWith( "/visual-captures" ) ) {
					this.showCaptures( this.dialog_site.site.site_id )
				}
			});
		},
		fetchSiteDetails( site_id ) {
			var data = {
				'action': 'captaincore_ajax',
				'command': "fetch-site-details",
				'post_id': site_id
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					Object.keys( response.data.site ).forEach( key => {
						this.dialog_site.site[ key ] = response.data.site[ key ]
					})
					this.dialog_site.site.account = response.data.account
					this.dialog_site.site.domains = response.data.domains
					this.dialog_site.site.shared_with = response.data.shared_with
				});
		},
		fetchSiteInfo( site_id ) {
			var data = {
				'action': 'captaincore_ajax',
				'command': "fetch-site",
				'post_id': site_id
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					response.data.forEach( site => {
						lookup = this.sites.filter(s => s.site_id == site.site_id).length;
						if (lookup == 1 ) {
							// Update existing site info
							site_update = this.sites.filter(s => s.site_id == site.site_id)[0];
							// Look through keys and update
							Object.keys(site).forEach(function(key) {
								// Skip updating environment_selected and tabs_management
								if ( key == "environment_selected" || key == "tabs" || key == "tabs_management" ) {
									return;
								}
							site_update[key] = site[key];
							})
						}
						if (lookup != 1 ) { 
							// Add new site info
							this.sites.push( site )
						}
						if ( this.dialog_site.site.site_id == site.site_id ) {
							site_update = this.sites.filter( s => s.site_id == site.site_id )[0]
							this.showSite( site_update )
						}
					});
				});
		},
		fetchMissing() {
			if ( this.allDomains == 0 && this.modules.dns && this.domains_loading ) {
				this.fetchDomains()
			}
			if ( this.sites_loading ) {
				this.fetchSites()
			}
			if ( this.role == 'administrator' && this.users.length == 0 ) {
				this.fetchAllUsers()
			}
		},
		fetchProviders() {
			axios.get(
				'/wp-json/captaincore/v1/providers', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.providers = response.data
				});
		},
		fetchDomains() {
			axios.get(
				'/wp-json/captaincore/v1/domains', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.domains = response.data
					this.domains_loading = false
					this.loading_page = false
					if ( this.dialog_domain.step == 2 && this.route_path != "" ) {
						domain = this.domains.filter( d => d.domain_id == this.route_path )[0]
						this.modifyDNS( domain )
						this.fetchDomain( domain )
					}
					setTimeout(this.fetchMissing, 4000)
				})
		},
		fetchAllUsers() {
			axios.get(
				'/wp-json/captaincore/v1/users', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.users = response.data
					this.loading_page = false
				});
		},
		fetchRecipes() {
			axios.get(
				'/wp-json/captaincore/v1/recipes', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.recipes = response.data;
				});
		},
		fetchRunningProcesses() {
			this.dialog_processes.loading = true
			axios.get(
				'/wp-json/captaincore/v1/running', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.dialog_processes.processes = response.data
					this.listenProcesses()
				});
		},
		listenProcesses() {
			var data = {
				'action': 'captaincore_ajax',
				'command': 'listenProcesses',
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					procesess = this.dialog_processes.processes
					this.dialog_processes.loading = false
					this.dialog_processes.conn = new WebSocket( this.socket )
					this.dialog_processes.conn.onopen = () => this.dialog_processes.conn.send( '{ "token" : "'+ response.data +'", "action" : "start" }' )
					this.dialog_processes.conn.onmessage = (session) => {
						if ( session.data == "Error: signal: killed" ) {
							return
						}
						process_update = JSON.parse( session.data )
						results = procesess.filter( p => p.process_id == process_update.process_id )
						if ( results.length == 1 ) {
							results[0].status = process_update.status
							results[0].percentage = process_update.percentage
							results[0].completed_at = process_update.completed_at
						}
						if ( results.length == 0 ) {
							this.dialog_processes.processes.unshift( { command: process_update.command, created_at: process_update.created_at, process_id: process_update.process_id, status: process_update.status, percentage: process_update.percentage })
						}
					}
					this.dialog_processes.conn.onclose = () => {
						this.dialog_processes.conn.send( '{ "token" : "'+ response.data +'", "action" : "kill" }' )
					}
				})
				.catch( error => console.log( error ) );

		},
		fetchProcesses() {
			axios.get(
				'/wp-json/captaincore/v1/processes', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.processes = response.data;
					setTimeout(this.fetchMissing, 1000)
				});
		},
		fetchKeys() {
			if ( this.role != 'administrator' ||  this.role != 'owner' ) {
				return
			}
			axios.get(
				'/wp-json/captaincore/v1/keys', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.keys = response.data;
					this.loading_page = false;
					setTimeout(this.fetchMissing, 4000)
				});
		},
		fetchDefaults() {
			if ( this.role != 'administrator' && this.role != 'owner' ) {
				return
			}
			axios.get(
				'/wp-json/captaincore/v1/defaults', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.defaults = response.data;
					this.loading_page = false;
					setTimeout(this.fetchMissing, 4000)
				});
		},
		fetchAccounts() {
			axios.get(
			'/wp-json/captaincore/v1/accounts', {
				headers: {'X-WP-Nonce':this.wp_nonce}
			})
			.then(response => {
				this.accounts = response.data
				if ( this.dialog_account.step == 2 && this.route_path != "" ) {
					this.showAccount( this.route_path )
				}
				setTimeout(this.fetchMissing, 1000)
			});
		},
		fetchAccountPortals() {
		},
		fetchConfigurations() {
			axios.get(
			'/wp-json/captaincore/v1/configurations', {
				headers: {'X-WP-Nonce':this.wp_nonce}
			})
			.then(response => {
				this.configurations = response.data
				this.configurations_loading = false
				setTimeout(this.fetchMissing, 1000)
			});
		},
		fetchSubscriptions() {
			axios.get(
			'/wp-json/captaincore/v1/upcoming_subscriptions', {
				headers: {'X-WP-Nonce':this.wp_nonce}
			}).then(response => {
				revenue      = response.data.revenue
				transactions = response.data.transactions

				this.revenue_estimated = revenue
				
				new frappe.Chart( "#plan_chart", {
					data: {
						labels: Object.keys( revenue ),
						datasets: [
							{
								name: "Revenue",
								values: Object.values( revenue ),
							},
						],
						yRegions: [
							{ label: "", start: 0, end: 50, options: { labelPos: "right" } }
						],
					},
					tooltipOptions: {
						formatTooltipY: d => '$' + d,
					},
					type: "bar",
					height: 270,
					colors: [ this.configurations.colors.primary, this.configurations.colors.success ],
					barOptions: {
						spaceRatio: 0.1,
					},
					axisOptions: {
						xAxisMode: "tick",
						xIsSeries: true
					},
					lineOptions: {
						regionFill: 1 // default: 0
					},
				})
				new frappe.Chart( "#plan_chart_transactions", {
					data: {
						labels: Object.keys( revenue ),
						datasets: [
							{
								name: "Transactions",
								values: Object.values( transactions ),
							},
						],
					},
					type: "bar",
					height: 270,
					colors: [ this.configurations.colors.primary, this.configurations.colors.success ],
					barOptions: {
						spaceRatio: 0.1,
					},
					axisOptions: {
						xAxisMode: "tick",
						xIsSeries: true
					},
					lineOptions: {
						regionFill: 1 // default: 0
					},
				})

			})

			axios.get(
			'/wp-json/captaincore/v1/subscriptions', {
				headers: {'X-WP-Nonce':this.wp_nonce}
			})
			.then(response => {
				this.subscriptions = response.data
			})
		},
		fetchBilling() {
			axios.get(
			'/wp-json/captaincore/v1/billing', {
				headers: {'X-WP-Nonce':this.wp_nonce}
			})
			.then(response => {
				this.billing = response.data
				default_payment = this.billing.payment_methods.filter( method => method.is_default )
				if ( default_payment.length == 1 ) {
					this.billing.payment_method = default_payment[0].token
				}
				this.billing_loading = false
				if ( this.billing.address.country != "" ) {
					this.populateStates()
				}
				setTimeout(this.fetchMissing, 1000)
			});
		},
		populateCloneSites(destination_provider_id) {
			let all_other_sites = [];
			
			// this.kinsta_provider_sites is the array: [{"11": [...]}, {"12": [...]}]
			this.kinsta_provider_sites.forEach(providerObject => {
				// Get the key from the object (e.g., "11" or "12")
				const providerId = Object.keys(providerObject)[0];
				
				if (providerId == destination_provider_id) {
					// Get the array of sites for this provider
					const sites = providerObject[providerId];
					// Add these sites to our collection
					all_other_sites = all_other_sites.concat(sites);
				}
			});
			
			// Update the component's data property
			this.clone_sites = all_other_sites;
		},
		populateStatesFor( item ) {
			states_selected = []
			select = this.states[ item.country ]
			if ( typeof select != 'object' ) {
				this.states_selected = []
				return
			}
			states_by_country = Object.entries( select )
			states_by_country.forEach( ([key, value]) => {
				states_selected.push( { "title": value, "value": key } )
			})
			this.states_selected = states_selected
		},
		populateStatesforContacts() {
			const contacts = ["owner", "admin", "tech", "billing"];
			const key = contacts[this.dialog_domain.contact_tabs];
			
			if (this.dialog_domain.provider.contacts && this.dialog_domain.provider.contacts[key]) {
				this.populateStatesFor(this.dialog_domain.provider.contacts[key]);
			} else {
				// Optionally handle the case where the contact object doesn't exist
				// for the selected tab, e.g., by clearing the states.
				this.states_selected = [];
			}
		},
		populateStates() {
			states_selected = []
			select = this.states[ this.billing.address.country ]
			if ( typeof select != 'object' ) {
				this.states_selected = []
				return
			}
			states_by_country = Object.entries( select )
			states_by_country.forEach( ([key, value]) => {
				states_selected.push( { "title": value, "value": key } )
			})
			this.states_selected = states_selected
		},
		fetchSites() {
			// Check keys if admin/owner
			if ((this.role == 'administrator' || this.role == 'owner') && this.keys.length == 0) {
				axios.get('/wp-json/captaincore/v1/keys', {
					headers: { 'X-WP-Nonce': this.wp_nonce }
				}).then(response => {
					this.keys = response.data;
				});
			}

			// Fetch Sites
			axios.get('/wp-json/captaincore/v1/sites', {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				let incomingSites = response.data;

				// 1. Check if filters are active in the UI
				if (this.isAnySiteFilterActive) {
					
					// 2. If we have cached matches (environment IDs), apply them locally immediately
					if (this.filtered_environment_ids && this.filtered_environment_ids.length > 0) {
						// Use a Set for O(1) lookups
						const allowedEnvIds = new Set(this.filtered_environment_ids);

						incomingSites.forEach(site => {
							// A site is visible (filtered=true) if ANY of its environments match the filter list
							if (site.environments && site.environments.length > 0) {
								site.filtered = site.environments.some(env => allowedEnvIds.has(env.environment_id));
							} else {
								site.filtered = false;
							}
						});
					} else {
						// 3. Filters are active but we have no cached IDs (stale state).
						// Hide everything temporarily to prevent flashing wrong data, then re-run server filter.
						incomingSites.forEach(s => s.filtered = false);
						this.filterSites();
					}
				} else {
					// 4. No filters active, everything is visible
					incomingSites.forEach(site => site.filtered = true);
				}

				// 5. Assign to state
				this.sites = incomingSites;
				
				this.sites_loading = false;
				this.loading_page = false;
				this.triggerPath();
				setTimeout(this.fetchMissing, 1000);
			})
			.catch(error => {
				console.error(error);
				this.sites_loading = false;
			});
		},
		fetchEnvironments() {
			axios.get(
				'/wp-json/captaincore/v1/environments', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.environments = response.data
			})
		},
		shareStats() {
			if ( ! this.dialog_site.environment_selected.stats.site.sharing ) {
				return
			}
			if ( this.dialog_site.environment_selected.stats.site.sharing == 'private' && this.dialog_site.environment_selected.stats_password == '' ) {
				return
			}
			var data = {
				action: 'captaincore_ajax',
				post_id: this.dialog_site.site.site_id,
				command: 'shareStats',
				fathom_id: this.dialog_site.environment_selected.stats.site.id,
				sharing: this.dialog_site.environment_selected.stats.site.sharing
			}

			if ( this.dialog_site.environment_selected.stats_password != '' ) {
				data.share_password = this.dialog_site.environment_selected.stats_password
			}

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => { 
					this.snackbar.message = "Stats sharing is " + this.dialog_site.environment_selected.stats.site.sharing
					this.snackbar.show = true
			})
		},
		handleDateChange(date, field) {
			// Use dayjs to format the date object to the required YYYY-MM-DD format
			const formattedDate = dayjs(date).format('YYYY-MM-DD');

			if (field === 'from_at') {
				this.stats.from_at = formattedDate;
				this.stats.from_at_select = false;
			} else if (field === 'to_at') {
				this.stats.to_at = formattedDate;
				this.stats.to_at_select = false;
			}

			// Now that the data is correctly formatted, fetch the stats
			this.fetchStats();
		},
		fetchStats() {

			fathom_id = this.dialog_site.environment_selected.stats.fathom_id
			environment = this.dialog_site.environment_selected
			environment.stats = "Loading";

			var data = {
				action: 'captaincore_ajax',
				post_id: this.dialog_site.site.site_id,
				command: 'fetchStats',
				from_at: this.stats.from_at,
				to_at: this.stats.to_at,
				grouping: this.stats.grouping,
				environment: this.dialog_site.environment_selected.environment
			}

			if ( fathom_id != "" ) {
				data.fathom_id = fathom_id
			}

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {

					if ( response.data.Error ) {
						environment.stats = response.data.Error 
						return;
					}

					if ( response.data.errors ) {
						environment.stats = response.data.errors 
						return;
					}

					chart_id = "chart_" + this.dialog_site.site.site_id + "_" + this.dialog_site.environment_selected.environment;
					chart_dom = document.getElementById( chart_id );		
					chart_dom.innerHTML = ""

					environment.stats = response.data
					names = environment.stats.items.map( s => s.date )
					pageviews = environment.stats.items.map( s => s.pageviews )
					visitors = environment.stats.items.map( s => s.visits )
					
					// Generate chart
					environment.chart = new frappe.Chart( "#" + chart_id, {
						data: {
							labels: names,
							datasets: [
								{
									name: "Pageviews",
									values: pageviews,
								},
								{
									name: "Visitors",
									values: visitors,
								},
							],
						},
						type: "line",
						height: 270,
						colors: [ this.configurations.colors.secondary, this.configurations.colors.primary ],
						axisOptions: {
							xAxisMode: "tick",
							xIsSeries: true
						},
						lineOptions: {
							regionFill: 1,
							hideDots: 1
						},
					})
				})
				.catch( error => console.log( error ) );

		},
		fetchUsers() {
			site = this.dialog_site.site
				var data = {
					'action': 'captaincore_ajax',
					'post_id': site.site_id,
					'command': "fetch-users",
			}
				axios.post( ajaxurl, Qs.stringify( data ) )
					.then( response => {
						response = response.data
						// Loop through environments and assign users
						Object.keys(response).forEach( key => {
							site.environments.filter( e => e.environment == key )[0].users = response[key];
							if ( response[key] == null ) {
								site.environments.filter( e => e.environment == key )[0].users = [];
							}
					})
				})
		},
		bulkEdit ( site_id, type ) {
			this.bulk_edit.show = true;
			site = this.dialog_site.site
			this.bulk_edit.site_id = site_id;
			this.bulk_edit.site_name = this.dialog_site.environment_selected.home_url
			this.bulk_edit.items = this.dialog_site.environment_selected[ type.toLowerCase() + "_selected" ];
			this.bulk_edit.type = type;
		},
		bulkEditExecute ( action ) {
			const site_id = this.bulk_edit.site_id;
			const site = this.dialog_site.site;
			const env = this.dialog_site.environment_selected;
			const object_type = this.bulk_edit.type;
			const object_singular = this.bulk_edit.type.slice(0, -1);
			
			let items = this.bulk_edit.items.map(item => item.name).join(" ");
			if ( object_singular == "user" ) {
				items = this.bulk_edit.items.map(item => item.user_login).join(" ");
			}

			// Start job
			const site_name = this.bulk_edit.site_name;
			const description = "Bulk action '" + action + " " + this.bulk_edit.type + "' on " + site_name;
			const jobId = Math.round((new Date()).getTime());
			
			this.jobs.push({
				"job_id": jobId, 
				"description": description, 
				"status": "queued", 
				"stream": []
			});

			// WP ClI command to send
			const wpcli = `wp ${object_singular} ${action} ${items} --skip-themes --skip-plugins`;

			// Set to loading.
			site.environments[0][ object_type ] = "Updating";
			if (site.environments[1] ) {
				site.environments[1][ object_type ] = "Updating";
			}

			this.bulk_edit.show = false;

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: [env.environment_id],
				code: wpcli
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand( response.data );
				}
			})
			.catch(error => {
				console.log(error.response);
			});
		},
		fetchLink( site_id, snapshot_id ) {
			site = this.dialog_site.site
			snapshot = this.dialog_site.environment_selected.snapshots.filter( s => s.snapshot_id == snapshot_id )[0];

			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': 'fetchLink',
				'environment': this.dialog_site.environment_selected.environment,
				'value': snapshot_id
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					snapshot.token = response.data.token;
					snapshot.expires_at = response.data.expires_at;
				})
				.catch( error => console.log( error ) );
		},
		promptBackupSnapshot( site_id ) {
			site = this.dialog_site.site
			this.dialog_backup_snapshot.show = true;
			this.dialog_backup_snapshot.site = site;
		},
		downloadBackupSnapshot( site_id ) {

			var post_id = this.dialog_backup_snapshot.site.site_id;
			var site_name = this.dialog_backup_snapshot.site.name;
			var environment = this.dialog_backup_snapshot.site.environment_selected;

			// Start job
			description = "Downloading snapshot for " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			var data = {
				'action': 'captaincore_install',
				'post_id': post_id,
				'command': 'snapshot',
				'environment': environment,
				'value': this.dialog_backup_snapshot.email,
				'notes': "User requested full snapshot"
			};

			if ( this.dialog_backup_snapshot.filter_toggle === false ) {
				data.filters = this.dialog_backup_snapshot.filter_options
				description = this.dialog_backup_snapshot.filter_options.join(", ").replace(/,([^,]*)$/,' and$1');
				data.notes = "User requested snapshot containing " + description;
			}
			
			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
					self.snackbar.message = "Generating snapshot for "+ self.dialog_backup_snapshot.site.name + ".";
					self.snackbar.show = true;
					self.dialog_backup_snapshot.site = {};
					self.dialog_backup_snapshot.show = false;
					self.dialog_backup_snapshot.email = self.dialog_backup_snapshot.current_user_email;
				})
				.catch( error => console.log( error ) );

		},
		copySite( site_id ) {
			site = this.dialog_site.site
			site_name = site.name;
			this.dialog_copy_site.show = true;
			this.dialog_copy_site.site = site;
			this.dialog_copy_site.options = this.sites.map(site => {
				option = { name: site.name, id: site.site_id };
				return option;
			}).filter(option => option.name != site_name );

			this.sites.map(site => site.name).filter(site => site != site_name );
		},
		editSite() {
			this.dialog_edit_site.site = JSON.parse ( JSON.stringify ( this.dialog_site.site ) )
			this.dialog_site.step = 4
		},
		cancelSiteRemoved() {
			site_id = this.dialog_site.site.site_id
			this.dialog_site.site.removed = false
			this.snackbar.message = `Cancelling removal request for ${this.dialog_site.site.name}.`
			this.snackbar.show = true
			axios.post( `/wp-json/captaincore/v1/sites/${site_id}`, {
				details: {
					removed: false
				}
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
		},
		markSiteRemoved() {
			site_id = this.dialog_site.site.site_id
			this.dialog_site.site.removed = true
			this.snackbar.message = `Marking ${this.dialog_site.site.name} for removal.`
			this.snackbar.show = true
			axios.post( `/wp-json/captaincore/v1/sites/${site_id}`, {
				details: {
					removed: true
				}
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
		},
		deleteSite( site_id ) {
			site = this.dialog_site.site
			site_name = site.name;
			should_proceed = confirm("Delete site " + site_name + "?");

			if ( ! should_proceed ) {
				return;
			}
			
			this.dialog_site.step = 1;

			axios.delete( `/wp-json/captaincore/v1/sites/${site_id}`, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.goToPath( '/sites' )
				// Update local list
				this.sites = this.sites.filter( site => site.site_id != site_id )
				this.snackbar.message = response.data.message
				this.snackbar.show = true
			})
			.catch( error => {
				console.log( error )
				this.snackbar.message = "Error deleting site."
				this.snackbar.show = true
			});
		},
		startCopySite() {
			site_name = this.dialog_copy_site.site.name;
			destination_id = this.dialog_copy_site.destination;
			site_name_destination = this.sites.filter(site => site.site_id == destination_id)[0].name;
			should_proceed = confirm("Copy site " + site_name + " to " + site_name_destination);

			if ( ! should_proceed ) {
				return;
			}

			var post_id = this.dialog_copy_site.site.site_id;

			var data = {
				'action': 'captaincore_install',
				'post_id': post_id,
				'command': 'copy',
				'value': this.dialog_copy_site.destination
			};

			self = this;

			// Start job
			description = "Coping "+ site_name + " to " + site_name_destination;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
					self.dialog_copy_site.site = {};
					self.dialog_copy_site.show = false;
					this.dialog_copy_site.destination = "";
					this.dialog_copy_site.options = [];
					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );

		},
		applyHttpsUrls( command ) {

			const site_id = this.dialog_apply_https_urls.site_id;
			const site_name = this.dialog_apply_https_urls.site_name;
			let envIds = [];

			if ( Array.isArray( site_id ) ) { 
				// Bulk context: resolve IDs based on selected sites and the global environment toggle (Production/Staging)
				const envName = this.dialog_bulk_tools.environment_selected || "Production";
				envIds = this.getEnvironmentIdsFromSelection(this.sites_selected, envName);
			} else {
				// Single site context: use the currently viewed environment
				if (this.dialog_site.environment_selected && this.dialog_site.environment_selected.environment_id) {
					envIds = [this.dialog_site.environment_selected.environment_id];
				}
			}

			if (envIds.length === 0) {
				this.snackbar.message = "No target environments found.";
				this.snackbar.show = true;
				return;
			}

			let should_proceed = confirm("Will apply ssl urls to '"+site_name+"'. Proceed?");

			if ( ! should_proceed ) {
				return;
			}

			// Start job
			const description = "Applying HTTPS urls to " + site_name;
			const job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			const extra_params = {};
            if (command === 'apply-https-with-www') {
                extra_params.www = true;
            }

			// Use the generic bulk-tools endpoint which handles standard commands like apply-https
			axios.post('/wp-json/captaincore/v1/sites/bulk-tools', {
				tool: 'apply-https',
				environments: envIds,
				params: extra_params
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				// Updates job id with the returned operation ID to start WebSocket streaming
				const job = this.jobs.find(j => j.job_id == job_id);
				if (job) {
					job.job_id = response.data;
					this.runCommand( response.data );
				}
				
				this.dialog_apply_https_urls.site_id = "";
				this.dialog_apply_https_urls.site_name = "";
				this.dialog_apply_https_urls.show = false;
				this.snackbar.message = "Applying HTTPS Urls";
				this.snackbar.show = true;
			})
			.catch(error => {
				const job = this.jobs.find(j => j.job_id == job_id);
				if (job) job.status = "error";
				console.error(error);
				this.snackbar.message = "Error: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
			});
		},
		fetchProcessLogs() {
			this.dialog_log_history.loading = true
			this.dialog_log_history.show = true;
			var data = {
				action: 'captaincore_ajax',
				command: 'fetchProcessLogs',
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_log_history.logs = response.data
					this.dialog_log_history.loading = false
				})
				.catch( error => console.log( error ) );
		},
		showLogEntry( site_id ){
			site = this.dialog_site.site
			this.dialog_new_log_entry.show = true;
			this.dialog_new_log_entry.sites = [];
			this.dialog_new_log_entry.sites.push( site );
			this.dialog_new_log_entry.site_name = site.name;
		},
		exportTimeline() {
			this.$refs.export_json.download = "timeline.json";
            this.$refs.export_json.href = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify({
				site: { 
					name: this.dialog_site.site.name,
					site_id: this.dialog_site.site.site_id,
				},
                entries: this.dialog_site.site.timeline
            }, null, 2));
            this.$refs.export_json.click();
		},
		showLogEntryBulk() {
			this.dialog_new_log_entry.show = true;
			this.dialog_new_log_entry.sites = this.sites_selected;
			this.dialog_new_log_entry.site_name = this.sites_selected.length + " sites";
		},
		showLogEntryGeneric() {
			this.dialog_new_log_entry.show = true;
			this.dialog_new_log_entry.sites = [];
		},
		newLogEntry() {
			site_ids = this.dialog_new_log_entry.sites.map( s => s.site_id )
			var data = {
				action: 'captaincore_ajax',
				post_id: site_ids,
				process_id: this.dialog_new_log_entry.process,
				command: 'newLogEntry',
				value: this.dialog_new_log_entry.description
			}
			this.dialog_new_log_entry.show = false
			this.dialog_new_log_entry.sites = []
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					Object.keys(response.data).forEach( site_id => {
						if ( site_id == this.dialog_site.site.site_id ) {
							this.dialog_site.site.timeline = response.data[site_id]
						}
					})
					this.dialog_new_log_entry.sites = []
					this.dialog_new_log_entry.site_name = ""
					this.dialog_new_log_entry.description = ""
					this.dialog_new_log_entry.process = ""
				})
				.catch( error => console.log( error ) )
		},
		updateLogEntry() {
			const logData = this.dialog_edit_log_entry.log;
			const processLogId = logData.process_log_id;

			this.dialog_edit_log_entry.show = false;

			axios.post(`/wp-json/captaincore/v1/process-logs/${processLogId}`, logData, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				Object.keys(response.data).forEach(site_id => {
					if (site_id == this.dialog_site.site.site_id) {
						this.dialog_site.site.timeline = response.data[site_id];
					}
				});
				this.dialog_edit_log_entry.log = {};
			})
			.catch(error => {
				console.error("Error updating log entry:", error);
			});
		},
		editScript( script ) {
			this.dialog_edit_script.show = true
			this.dialog_edit_script.script.script_id = script.script_id
			this.dialog_edit_script.script.code = script.code
			d = new Date(0);
			d.setUTCSeconds(script.run_at);
			this.dialog_edit_script.script.run_at_date = d.toLocaleDateString("en-CA")
			this.dialog_edit_script.script.run_at_time = d.toLocaleTimeString("en-US", {
					hour: '2-digit',
					minute: '2-digit',
					hour12: false, // Use 24-hour format
				});
		},
		updateScript() {
			axios.post( `/wp-json/captaincore/v1/scripts/${this.dialog_edit_script.script.script_id}`, {
					code: this.dialog_edit_script.script.code,
					run_at: {
						time: this.dialog_edit_script.script.run_at_time,
						date: this.dialog_edit_script.script.run_at_date,
						timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
					}
				}, {
					headers: { 'X-WP-Nonce':this.wp_nonce }
				})
				.then( response => {
					this.snackbar.message = `Updated code to run on ${this.dialog_site.environment_selected.home_url} at ${this.script.time} ${this.script.date}.`
					this.snackbar.show = true
					this.script.code = "";
					this.script.menu = false;
					this.script.menu_date = false;
					this.script.menu_time = false;
					this.script.time = "";
					this.script.date = "";
					this.dialog_edit_script = { show: false, script: { script_id: "", code: "", run_at_time: "", run_at_date: "" } }
					this.fetchSiteEnvironments( this.dialog_site.site.site_id )
				});
		},
		editLogEntry( site_id, log_id ) {

			// If not assigned that's fine but at least assign as string.
			if ( site_id == "" ) {
				site_id = "Not found";
			}

			if ( typeof site_id == "object" ) {
				site_id = site_id[0].site_id;
			}
			
			site = this.dialog_site.site

			var data = {
				action: 'captaincore_ajax',
				command: 'fetchProcessLog',
				post_id: site_id,
				value: log_id,
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_edit_log_entry.log = response.data;
					this.dialog_edit_log_entry.show = true;
					if ( typeof site !== "undefined" ) {
						this.dialog_edit_log_entry.site = site;
					} else {
						this.dialog_edit_log_entry.site = {};
					}
				})
				.catch( error => console.log( error ) );

		},
		viewProcess( process_id ) {

			process = this.processes.filter( process => process.process_id == process_id )[0];
			this.dialog_handbook.process = process;
			this.dialog_handbook.process.description = "Loading...";
			this.dialog_handbook.show = true;

			var data = {
				action: 'captaincore_ajax',
				post_id: process_id,
				command: 'fetchProcess',
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_handbook.process = response.data
				})
				.catch( error => console.log( error ) )

		},
		editProcess() {
			this.dialog_handbook.show = false
			var data = {
				action: 'captaincore_ajax',
				post_id: this.dialog_handbook.process.process_id,
				command: 'fetchProcessRaw',
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_edit_process.process = response.data;
					this.dialog_edit_process.show = true;
				})
				.catch( error => console.log( error ) );
		},
		saveProcess() {
			var data = {
				action: 'captaincore_ajax',
				command: 'saveProcess',
				value: this.dialog_edit_process.process
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.fetchProcesses()
					this.dialog_edit_process = { show: false, process: {} }
				})
				.catch( error => console.log( error ) )
		},
		addNewProcess() {
			var data = {
				action: 'captaincore_ajax',
				command: 'newProcess',
				value: this.new_process
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.fetchProcesses()
					this.new_process = { show: false, name: "", time_estimate: "", repeat_interval: "as-needed", repeat_quantity: "", roles: "", description: "" }
				})
				.catch( error => console.log( error ) )

		},
		addNewKey() {
			var data = {
				action: 'captaincore_ajax',
				command: 'newKey',
				value: this.new_key
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.keys.unshift( response.data );
					this.new_key = { show: false, title: "", key: "" };
					this.snackbar.message = "New SSH key added.";
					this.snackbar.show = true;
				})
				.catch( error => console.log( error ) );
		},
		viewKey( key_id ) {
			key = this.keys.filter( key => key.key_id == key_id )[0];
			this.dialog_key.key = key;
			this.dialog_key.key.key = "";
			this.dialog_key.show = true;
		},
		setKeyAsPrimary() {
			key_title = this.dialog_key.key.title
			should_proceed = confirm(`Set SSH key '${key_title}' as primary key?`);
			if ( ! should_proceed ) {
				return;
			}
			var data = {
				action: 'captaincore_ajax',
				command: 'setKeyAsPrimary',
				value: this.dialog_key.key
			};
			key = this.keys.filter( key => key.key_id == this.dialog_key.key.key_id )[0]
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.fetchKeys()
					this.dialog_key = { show: false, key: {} }
				})
				.catch( error => console.log( error ) );
		},
		updateKey() {
			var data = {
				action: 'captaincore_ajax',
				command: 'updateKey',
				value: this.dialog_key.key
			};
			key = this.keys.filter( key => key.key_id == this.dialog_key.key.key_id )[0];
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.keys = this.keys.filter( key => key.key_id != this.dialog_key.key.key_id )
					this.dialog_key = { show: false, key: {} };
					this.keys.push( response.data );
					this.keys.sort((a, b) => (a.title > b.title) ? 1 : -1)
				})
				.catch( error => console.log( error ) );
		},
		deleteKey() {
			delete_key = this.keys.filter( key => key.key_id == this.dialog_key.key.key_id )[0];
			should_proceed = confirm(`Delete SSH key '${delete_key.title}'?`);
			if ( ! should_proceed ) {
				return;
			}
			var data = {
				action: 'captaincore_ajax',
				command: 'deleteKey',
				value: this.dialog_key.key.key_id
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.keys = this.keys.filter( key => key.key_id != this.dialog_key.key.key_id )
					this.dialog_key = { show: false, key: {} };
				})
				.catch( error => console.log( error ) );
		},
		fetchInviteInfo(){
			var data = {
				action: 'captaincore_local',
				command: 'fetchInvite',
				value: this.fetchInvite
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.new_invite = response.data
				})
				.catch( error => console.log( error ) );
		},
		newUser( dialog ) {
			var data = {
				'action': 'captaincore_local',
				'command': 'newUser',
				'value': this.dialog_new_user,
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					if ( response.data.errors ) {
						this.dialog_new_user.errors = response.data.errors
						return
					}
					this.fetchAllUsers()
					this.snackbar.message = "New user added."
					this.snackbar.show = true
					dialog.value = false
					this.dialog_new_user = { first_name: "", last_name: "", email: "", login: "", account_ids: [], errors: [] }
				})
				.catch( error => console.log( error ) );
		},
		disableTFA() {
			axios.get(
				`/wp-json/captaincore/v1/me/tfa_deactivate`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					if ( response.data ) {
						this.profile.tfa_activate = false
						this.profile.tfa_enabled = false
						this.snackbar.message = "Two-Factor Authentication has been disabled."
						this.snackbar.show = true
						return
					}
					this.snackbar.message = "Token is not valid."
				})
		},
		enableTFA() {
			axios.get(
				`/wp-json/captaincore/v1/me/tfa_activate`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.profile.tfa_uri = response.data
					this.profile.tfa_token = response.data.split("=").pop()
					let tfa_qr_code = document.getElementById("tfa_qr_code")
					tfa_qr_code.innerHTML = ""
					let qr_code = kjua({
						crisp: false,
						render: 'canvas',
						text: this.profile.tfa_uri,
						size: "150",
					})
					tfa_qr_code.appendChild(qr_code)
					this.profile.tfa_activate = true
				})
		},
		activateTFA() {
			axios.post(
				`/wp-json/captaincore/v1/me/tfa_validate`, {
					token: this.login.tfa_code
				}, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					if ( response.data ) {
						this.profile.tfa_activate = false
						this.profile.tfa_enabled = true
						this.snackbar.message = "Two-Factor Authentication has been enabled."
						this.snackbar.show = true
						return
					}
					this.snackbar.message = "Token is not valid."
					this.snackbar.show = true
				})
		},
		
		cancelTFA() {
			let tfa_qr_code = document.getElementById("tfa_qr_code")
			tfa_qr_code.innerHTML = ""
			this.profile.tfa_uri = ""
			this.profile.tfa_activate = false
		},
		updateAccount() {
			var data = {
				action: 'captaincore_local',
				command: 'updateAccount',
				value: this.profile,
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					if ( response.data.errors ) {
						this.profile.errors = response.data.errors
						return
					}
					this.snackbar.message = "Account updated."
					this.snackbar.show = true
					this.current_user_display_name = response.data.profile.display_name
					this.profile.errors = []
					this.profile.new_password = ""
				})
				.catch( error => console.log( error ) );
		},
		createAccount(){
			axios.post( '/wp-json/captaincore/v1/login/', {
					 command: "createAccount",
					 login: this.new_account,
					 invite: this.fetchInvite,
				})
				.then( response => {
					if ( response.data.errors ) {
						this.snackbar.message = response.data.errors.join(", ")
						this.snackbar.show = true
						return
					}
					this.snackbar.message = "New account created. Logging in..."
					this.snackbar.show = true
					window.location = "/account"
				})
				.catch( error => console.log( error ) );
		},
		removeAccountAccess( user_id ) {
			email = this.dialog_account.records.users.filter( u => u.user_id == user_id )[0].email
			should_proceed = confirm(`Remove access for user ${email}?`);
			if ( ! should_proceed ) {
				return;
			}
			var data = {
				action: 'captaincore_local',
				command: 'removeAccountAccess',
				value: user_id,
				account: this.dialog_account.records.account.account_id
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_account.records.users = this.dialog_account.records.users.filter( u => u.user_id != user_id )
					this.snackbar.message = `Removed access for user ${email}.`
					this.snackbar.show
					axios.get(
						'/wp-json/captaincore/v1/accounts', {
							headers: {'X-WP-Nonce':this.wp_nonce}
						})
						.then(response => {
							this.accounts = response.data;
						});
				})
				.catch( error => console.log( error ) );

		},
		deleteInvite( invite_id ) {
			email = this.dialog_account.records.invites.filter( i => i.invite_id == invite_id )[0].email
			should_proceed = confirm(`Delete invite ${email}?`);
			if ( ! should_proceed ) {
				return;
			}
			if ( invite_id == "" ) {
				return
			}
			var data = {
				action: 'captaincore_account',
				command: 'deleteInvite',
				account_id: this.dialog_account.records.account.account_id,
				value: invite_id
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_account.records.invites = this.dialog_account.records.invites.filter( i => i.invite_id != invite_id )
					this.snackbar.message = "Invite deleted."
					this.snackbar.show
				})
				.catch( error => console.log( error ) );
		},
		acceptInvite() {
			var data = {
				action: 'captaincore_local',
				command: 'acceptInvite',
				value: this.fetchInvite
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					window.history.pushState({}, document.title, window.location.origin + window.location.pathname );
					this.querystring = ""
					this.route = ""
					axios.get(
						'/wp-json/captaincore/v1/accounts', {
							headers: {'X-WP-Nonce':this.wp_nonce}
						})
						.then(response => {
							this.accounts = response.data;
						});
				})
				.catch( error => console.log( error ) );
		},
		cancelInvite() {
			window.history.pushState({}, document.title, window.location.origin + window.location.pathname );
			this.querystring = ""
			this.route = ""
		},
		fetchUserAccounts(userItem, isOpen) {
			// Only fetch when the menu is opening and data isn't already there or loading.
			if (!isOpen || userItem._accounts_data || userItem._accounts_loading) {
				return;
			}

			userItem._accounts_loading = true;

			axios.get(`/wp-json/captaincore/v1/users/${userItem.user_id}/accounts`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				const accountIds = response.data; // This is the array of IDs, e.g., [1, 23]
				
				// Map the IDs to full account objects from the master `this.accounts` list
				userItem._accounts_data = accountIds.map(id => {
					return this.accounts.find(acc => acc.account_id == id);
				}).filter(Boolean); // .filter(Boolean) removes any undefined results if an account wasn't found
			})
			.catch(error => {
				console.error(`Error fetching accounts for user ${userItem.user_id}:`, error);
				userItem._accounts_data = [{ name: 'Error loading accounts.' }]; 
			})
			.finally(() => {
				userItem._accounts_loading = false;
			});
		},
		editUser( user_id ) {
			var data = {
				action: 'captaincore_local',
				command: 'fetchUser',
				value: user_id
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_user.user = response.data
					this.dialog_user.show = true;
				})
				.catch( error => console.log( error ) );
		},
		saveUser() {
			var data = {
				action: 'captaincore_local',
				command: 'saveUser',
				value: this.dialog_user.user
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					if ( response.data.errors ) {
						this.dialog_user.errors = response.data.errors
						return
					}
					this.fetchAllUsers()
					this.snackbar.message = "User updated."
					this.snackbar.show = true
					this.dialog_user.show = false
					this.dialog_user.errors = []
					this.dialog_user.user = {}
				})
				.catch( error => console.log( error ) );
		},
		deletePaymentMethod( token ) {
			card = this.billing.payment_methods.filter( p => p.token == token )[0]
			should_proceed = confirm(`Delete payment method ${card.method.brand} ending in ${card.method.last4} with expiration ${card.expires}?`);

			if ( ! should_proceed ) {
				return
			}

			var data = {
				action: 'captaincore_account',
				command: 'deletePaymentMethod',
				value: token
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					if ( response.data.errors ) {
						console.log( response.data.errors )
						return
					}
					this.fetchBilling()
				})
				.catch( error => console.log( error ) );
		},
		setAsPrimary( token ) {
			var data = {
				action: 'captaincore_account',
				command: 'setAsPrimary',
				value: token
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					if ( response.data.errors ) {
						console.log( response.data.errors )
						return
					}
					this.fetchBilling()
				})
				.catch( error => console.log( error ) );
		},
		prepNewPayment() {
			elements = stripe.elements()
			style = {
				base: {
					color: "#32325d",
					fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
					fontSmoothing: "antialiased",
					fontSize: "16px",
					"::placeholder": {
						color: "#aab7c4"
					}
				},
				invalid: {
				color: "#fa755a",
				iconColor: "#fa755a"
				}
			}
			this.new_payment.card = elements.create("card", { style: style })
			this.new_payment.card.mount("#new-card-element")
		},
		downloadPDF() {
			var data = {
				action: 'captaincore_local',
				command: 'downloadPDF',
				value: this.dialog_invoice.response.order_id
			};
			axios.post( ajaxurl, Qs.stringify( data ), { responseType: 'blob' })
				.then( response => {
					newBlob = new Blob([response.data], {type: "application/pdf"})
					this.$refs.download_pdf.download = `invoice-${this.dialog_invoice.response.order_id}.pdf`;
					this.$refs.download_pdf.href = window.URL.createObjectURL(newBlob);
					this.$refs.download_pdf.click();
				})
				.catch( error => console.log( error ) );
		},
		showInvoice( order_id ) {
			this.dialog_invoice.loading = true
			var data = {
				action: 'captaincore_local',
				command: 'fetchInvoice',
				value: order_id
			};
			this.dialog_billing.step = 2
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					if ( response.data.errors ) {
						this.dialog_user.errors = response.data.errors
						return
					}
					this.dialog_invoice.response = response.data
					this.dialog_invoice.payment_method = this.billing.payment_method
					if ( typeof this.dialog_invoice.payment_method == 'undefined' ) {
						this.dialog_invoice.payment_method = "new"
					}
					if ( this.dialog_invoice.response.status == 'pending' || this.dialog_invoice.response.status == 'failed' ) {
						elements = stripe.elements()
						style = {
							base: {
								color: "#32325d",
								fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
								fontSmoothing: "antialiased",
								fontSize: "16px",
								"::placeholder": {
									color: "#aab7c4"
								}
							},
							invalid: {
							color: "#fa755a",
							iconColor: "#fa755a"
							}
						}
						this.dialog_invoice.card = elements.create("card", { style: style })
						this.dialog_invoice.card.mount("#card-element")
					}
					this.dialog_invoice.loading = false
					this.dialog_invoice.show = true
				})
				.catch( error => console.log( error ) );
		},
		payInvoice( invoice_id ) {
			if ( ! this.$refs.billing_form.validate() ) {
				this.snackbar.message = "Missing billing information"
				this.snackbar.show = true
				return
			}
        
			this.dialog_invoice.paying = true
			this.updateBilling()
			invoice_id = this.dialog_invoice.response.order_id
			self = this

			if ( this.dialog_invoice.payment_method == 'new' ) {
				stripe.createSource( this.dialog_invoice.card, {
					type: "card",
					currency: 'usd',
					owner: {
						name: this.billing.address.first_name + " " + this.billing.address.last_name,
						email: this.billing.address.email,
						address: {
							city: this.billing.address.city,
							country: this.billing.address.country,
							line1: this.billing.address.address_1,
							line2: this.billing.address.address_2,
							postal_code: this.billing.address.postcode,
							state: this.billing.address.state,
						},
					},
				}).then(function(result) {
					if ( result.error ) {
						self.dialog_invoice.paying = false
						self.dialog_invoice.error = result.error.message
						return
					}
					var data = {
						action: 'captaincore_account',
						command: 'payInvoice',
						value: invoice_id,
						source_id: result.source.id,
					}
					axios.post( ajaxurl, Qs.stringify( data ) )
						.then( response => {
							if ( response.data && response.data.error ) {
								self.dialog_invoice.paying = false;
								self.dialog_invoice.error = response.data.error;
								return;
							}
							self.dialog_invoice.paying = false
							self.showInvoice( invoice_id )
							self.fetchBilling()
						})
						.catch( error => {
							console.log( error );
							self.dialog_invoice.paying = false;
							self.dialog_invoice.error = "An unexpected error occurred. Please try again.";
						})
				})
				return
			}

			var data = {
				action: 'captaincore_account',
				command: 'payInvoice',
				value: invoice_id,
				payment_id: this.dialog_invoice.payment_method,
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_invoice.paying = false
					this.showInvoice( invoice_id )
					this.fetchBilling()
				})
				.catch( error => {
					console.log( error );
					this.dialog_invoice.paying = false;
					this.dialog_invoice.error = "An unexpected error occurred. Please try again.";
				})
		},
		addPaymentMethod() {
			self = this
			stripe.createSource( this.new_payment.card, {
					type: "card",
					currency: 'usd',
					owner: {
						name: this.billing.address.first_name + " " + this.billing.address.last_name,
						email: this.billing.address.email,
						address: {
							city: this.billing.address.city,
							country: this.billing.address.country,
							line1: this.billing.address.address_1,
							line2: this.billing.address.address_2,
							postal_code: this.billing.address.postcode,
							state: this.billing.address.state,
						},
					},
				}).then(function(result) {
					if ( result.error ) {
						self.new_payment.error = result.error.message
					}
					var data = {
						action: 'captaincore_account',
						command: 'addPaymentMethod',
						value: result.source.id
					};
					axios.post( ajaxurl, Qs.stringify( data ) )
						.then( response => {
							if ( response.data && response.data.error ) {
								self.new_payment.error = response.data.error;
								return;
							}
							self.fetchBilling()
							self.new_payment = { card: {}, show: false, error: "" }
						})
						.catch( error => {
							console.log( error );
							self.new_payment.error = "An unexpected error occurred. Please try again.";
						} )
			})
		},
		showAccount( account_id ) {
			this.dialog_account.loading = true;
			account = this.accounts.filter( account => account.account_id == account_id )[0];
			var data = {
				action: 'captaincore_local',
				command: 'fetchAccount',
				value: account_id
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_account.records = response.data
					this.dialog_account.show = true;
					this.dialog_account.step = 2;
					this.dialog_account.loading = false;
				})
				.catch( error => {
					console.log( error );
					this.dialog_account.loading = false;
				});
		},
		accountBulkTools() {
			// 1. Select the sites belonging to this account
			this.sites_selected = this.dialog_account.records.sites;
			
			// 2. Navigate to Sites tab
			this.goToPath('/sites');

			// 3. Open Terminal
			this.view_console.terminal_open = true;

			// 4. Populate Terminal Targets based on the account's sites
			// We filter to find the "Production" environment for these sites to target by default
			const targets = [];
			this.sites_selected.forEach(site => {
				// Find existing site object in main list to get full env data
				const fullSite = this.sites.find(s => s.site_id == site.site_id);
				if (fullSite && fullSite.environments) {
					const prod = fullSite.environments.find(e => e.environment === 'Production') || fullSite.environments[0];
					if (prod) {
						targets.push({
							site_id: fullSite.site_id,
							name: fullSite.name,
							environment_id: prod.environment_id,
							environment: prod.environment,
							home_url: prod.home_url
						});
					}
				}
			});

			this.view_console.selected_targets = targets;
		},
		editAccount() {
			this.dialog_edit_account.show = true
			this.dialog_edit_account.account = this.dialog_account.records.account
		},
		createSiteAccount() {
			axios.post(
				`/wp-json/captaincore/v1/accounts`, {
					name: this.dialog_new_account.name
				}, {
					headers: { 'X-WP-Nonce':this.wp_nonce }
				})
				.then(response => {
					this.fetchAccounts()
					this.dialog_new_account.show = false
					this.dialog_new_account.name = ""
					this.dialog_account.step = 1
				});
		},
		updateSiteAccount() {
			var data = {
				action: 'captaincore_ajax',
				command: 'updateSiteAccount',
				value: this.dialog_edit_account.account
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.fetchAccounts()
					this.dialog_edit_account.show = false
					this.showAccount( this.route_path )
				})
				.catch( error => console.log( error ) );
		},
		updateDomainAccount() {
			var data = {
				action: 'captaincore_ajax',
				command: 'updateDomainAccount',
				value: this.dialog_domain.account_ids,
				domain_id: this.dialog_domain.domain.domain_id,
				provider_id: this.dialog_domain.provider_id
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// If error then response
					if ( response.data.errors ) {
						this.snackbar.message = error
						this.snackbar.show = true
						return
					}
					this.snackbar.message = "Domain updated"
					this.snackbar.show = true
					this.fetchDomain( this.dialog_domain.domain )
				})
				.catch( error => {
					this.snackbar.message = error
					this.snackbar.show = true
				});
		},
		deleteAccount() {
			account = this.dialog_account.records.account
			
			should_proceed = confirm("Delete account " + account.name +"?");

			if ( ! should_proceed ) {
				return;
			}

			// Start job
			description = "Deleting account " + account.name;
			this.dialog_site.step = 1

			var data = {
				'action': 'captaincore_ajax',
				'command': 'deleteAccount',
				'post_id': account.account_id
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Remove item
					this.accounts = this.accounts.filter( a => a.account_id != account.account_id )
					this.snackbar.message = "Deleting account "+ account.name + "."
					this.goToPath( '/accounts' )
				})
				.catch( error => console.log( error ) );
		},
		sendAccountInvite() {
			var data = {
				action: 'captaincore_account',
				command: 'sendAccountInvite',
				account_id: this.dialog_account.records.account.account_id,
				invite: this.dialog_account.new_invite_email
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.snackbar.message = response.data.message
					this.snackbar.show = true
					this.dialog_account.new_invite_email = "" 
					this.dialog_account.new_invite = false
					this.showAccount( this.dialog_account.records.account.account_id )
				})

		},
		editRecipe( recipe_id ) {
			recipe = this.recipes.filter( recipe => recipe.recipe_id == recipe_id )[0];
			this.dialog_cookbook.recipe = recipe;
			this.dialog_cookbook.show = true;
		},
		loadRecipe( recipe_id ) {
			recipe = this.recipes.filter( recipe => recipe.recipe_id == recipe_id )[0];
			this.snackbar.message = "Recipe '"+ recipe.title +"' loaded.";
			this.snackbar.show = true;
			this.script.code = recipe.content;
			this.goTo( '#script_site', { offset: -70 } );
		},
		runRecipe( recipe_id, site_id ) {
			recipe = this.recipes.filter( recipe => recipe.recipe_id == recipe_id )[0];
			site_name =  this.dialog_site.environment_selected.home_url
			site_name = site_name.replace( "https://www.", "" ).replace( "https://", "" ).replace( "http://www.", "" ).replace( "http://", "" )
			
			should_proceed = confirm( `Run recipe '${recipe.title}' on ${site_name}?` );

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site.site_id,
				command: 'recipe',
				environment: this.dialog_site.environment_selected.environment,
				value: recipe_id
			};

			description = `Run recipe '${recipe.title}' on ${site_name}`

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", "command": "recipe", stream: []});

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
				})
				.catch( error => console.log( error ) );
		},
		deleteRecipe() {
			recipe = this.dialog_cookbook.recipe
			should_proceed = confirm( `Delete recipe ${recipe.title}?` )

			if ( ! should_proceed ) {
				return;
			}
			var data = {
				action: 'captaincore_ajax',
				command: 'updateRecipe',
				value: this.dialog_cookbook.recipe
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_cookbook.show = false;
					this.recipes = response.data;
				})
				.catch( error => console.log( error ) );
		},
		updateRecipe() {
			var data = {
				action: 'captaincore_ajax',
				command: 'updateRecipe',
				value: this.dialog_cookbook.recipe
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_cookbook.show = false;
					this.recipes = response.data;
				})
				.catch( error => console.log( error ) );
		},
		addRecipe() {
			var data = {
				action: 'captaincore_ajax',
				command: 'newRecipe',
				value: this.new_recipe
			};
			self = this;
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_recipe = { show: false, title: "", content: "" };
					self.recipes = response.data;
					self.new_recipe = { title: "", content: "" };
				})
				.catch( error => console.log( error ) );
		},
		handleRecipeClick(recipe) {
            if (recipe.public == 1) {
                // Public: Run immediately (Existing behavior)
                this.runRecipe( recipe.recipe_id, this.dialog_site.site.site_id );
            } else {
                // Private: Load into terminal for editing
                this.openTerminalWithRecipe(recipe);
            }
        },
        openTerminalWithRecipe(recipe) {
            // 1. Open Terminal targeting current environment
            this.openTerminalForCurrentEnv();
            
            // 2. Pre-fill the code
            this.script.code = recipe.content;
            
            // 3. Ensure input is focused
            this.$nextTick(() => {
                if (this.$refs.terminalInput) {
                    this.$refs.terminalInput.focus();
                }
            });
        },
		handleTerminalRecipeClick(recipe) {
            if (recipe.public == 1) {
                // Public: Run Immediately on selected targets
                this.runRecipeFromTerminal(recipe);
            } else {
                // Private: Load into input (Existing behavior)
                this.previewRecipeInInput(recipe);
            }
        },
        runRecipeFromTerminal(recipe) {
            const targets = this.view_console.selected_targets;
            if (!targets || targets.length === 0) {
                this.view_console.target_menu = true;
                this.snackbar.message = "Please select at least one target environment.";
                this.snackbar.show = true;
                return;
            }

            const confirmMsg = targets.length === 1 
                ? `Run recipe '${recipe.title}' on ${targets[0].home_url}?`
                : `Run recipe '${recipe.title}' on ${targets.length} environments?`;

            if (!confirm(confirmMsg)) return;

            // Construct payload similar to executeTerminalCommand
            const targetPayload = targets.map(t => {
                if (t.environment_id) return { enviroment_id: t.environment_id };
                return { site_id: t.site_id, environment: t.environment };
            });

            const description = `Running recipe '${recipe.title}' on ${targets.length} environment(s)`;
            const job_id = Math.round((new Date()).getTime());

            // Push Job
            this.jobs.push({ 
                "job_id": job_id, 
                "description": description, 
                "status": "queued", 
                "stream": [],
                "created_at": new Date()
            });

            // Execute via run/code endpoint using recipe content
            axios.post(`/wp-json/captaincore/v1/run/code`, {
                environments: targetPayload.map(t => t.enviroment_id || t),
                code: recipe.content
            }, {
                headers: { 'X-WP-Nonce': this.wp_nonce }
            })
            .then(response => {
                const job = this.jobs.find(j => j.job_id === job_id);
                if (job) {
                    job.job_id = response.data;
                    this.runCommand(response.data);
                }
                this.scrollToTerminalBottom();
            })
            .catch(error => {
                const job = this.jobs.find(j => j.job_id === job_id);
                if (job) {
                    job.status = "error";
                    job.stream.push("Error starting command: " + (error.response?.data?.message || error.message));
                }
            });
        },
		viewDomainMailgunLogs(domain) {
			this.dialog_mailgun.site = { name: domain.name }; // Reusing 'site' prop for title
			this.dialog_mailgun.domain_id = domain.domain_id; // Store ID for pagination
			this.dialog_mailgun.response.items = [];
			this.dialog_mailgun.show = true;
			this.fetchDomainMailgunLogs();
		},
		fetchDomainMailgunLogs(pageUrl = null) {
			// If pageUrl exists, we are appending (Load More), so use specific flag
			// Otherwise, we are doing a fresh load, so use the main loading flag
			if (pageUrl) {
				this.dialog_mailgun.loadingMore = true;
			} else {
				this.dialog_mailgun.loading = true;
			}
			
			let url = `/wp-json/captaincore/v1/domain/${this.dialog_mailgun.domain_id}/mailgun/events`;
			let params = {};
			
			if (pageUrl) {
				params.page_url = pageUrl;
			}

			axios.get(url, { 
				headers: { 'X-WP-Nonce': this.wp_nonce },
				params: params 
			})
			.then(response => {
				if (pageUrl) {
					this.dialog_mailgun.response.items = [
						...this.dialog_mailgun.response.items,
						...response.data.items
					];
					this.dialog_mailgun.response.paging = response.data.paging;
				} else {
					this.dialog_mailgun.response = response.data;
				}
			})
			.catch(error => {
				console.error(error);
				this.snackbar.message = "Error fetching logs: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
			})
			.finally(() => {
				// Turn off both loading flags
				this.dialog_mailgun.loading = false;
				this.dialog_mailgun.loadingMore = false;
			});
		},
		loadMoreMailgunLogs() {
			// Check if we have a next page link
			if (this.dialog_mailgun.response.paging && this.dialog_mailgun.response.paging.next) {
				this.fetchDomainMailgunLogs(this.dialog_mailgun.response.paging.next);
			}
		},
		viewMailgunEventDetails( item ) {
			this.dialog_mailgun_details.item = item
			this.dialog_mailgun_details.show = true
		},
		launchSiteDialog( site_id ) {
			if ( site_id ) {
				// Single site mode (triggered from Site Details > Scripts)
				const site = this.sites.find( s => s.site_id == site_id );
				this.dialog_launch.site = site || {};
				this.dialog_launch.mode = 'single';
			} else {
				// Bulk mode (triggered from Terminal System Tools)
				if (this.view_console.selected_targets.length === 0) {
					this.view_console.target_menu = true;
					this.snackbar.message = "Please select target environments.";
					this.snackbar.show = true;
					return;
				}
				// Create a dummy site object for the dialog display
				this.dialog_launch.site = { name: `${this.view_console.selected_targets.length} environments` };
				this.dialog_launch.mode = 'bulk';
			}
			
			this.dialog_launch.domain = "";
			this.dialog_launch.show = true;
		},
		launchSite() {
			if ( this.dialog_launch.domain == "" ) {
				this.snackbar.message = "Domain is required. Launch cancelled.";
				this.snackbar.show = true;
				return;
			}

			let envIds = [];
			const domain = this.dialog_launch.domain;
			const siteName = this.dialog_launch.site.name;

			// Determine Target Environments
			if ( this.dialog_launch.mode === 'bulk' ) {
				// Use terminal selections
				envIds = this.view_console.selected_targets.map(t => t.environment_id);
			} else {
				// Single site context
				const siteId = this.dialog_launch.site.site_id;
				
				// If we are currently viewing the site in the main dialog, use the selected environment
				if (this.dialog_site.site && this.dialog_site.site.site_id == siteId && this.dialog_site.environment_selected) {
					envIds = [this.dialog_site.environment_selected.environment_id];
				} else {
					// Fallback: Find Production environment for the site
					const fullSite = this.sites.find(s => s.site_id == siteId);
					if (fullSite && fullSite.environments) {
						const prod = fullSite.environments.find(e => e.environment === 'Production') || fullSite.environments[0];
						if (prod) envIds = [prod.environment_id];
					}
				}
			}

			if (envIds.length === 0) {
				this.snackbar.message = "Could not determine target environment for launch.";
				this.snackbar.show = true;
				return;
			}

			let should_proceed = confirm(`Launch ${siteName} to ${domain}? This will update URLs and configuration.`);
			if ( ! should_proceed ) {
				return;
			}

			const description = `Launching ${siteName} to ${domain}`;
			const job_id = Math.round((new Date()).getTime());
			
			this.jobs.push({
				"job_id": job_id,
				"description": description, 
				"status": "queued", 
				"stream": []
			});

			// Use the bulk-tools endpoint which handles 'launch' and params
			axios.post('/wp-json/captaincore/v1/sites/bulk-tools', {
				tool: 'launch',
				environments: envIds,
				params: {
					domain: domain
				}
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				const job = this.jobs.find(j => j.job_id == job_id);
				if (job) {
					job.job_id = response.data;
					this.runCommand( response.data );
				}
				
				this.dialog_launch.site = {};
				this.dialog_launch.domain = "";
				this.dialog_launch.show = false;
				this.snackbar.message = "Launch process started.";
				this.snackbar.show = true;
			})
			.catch( error => {
				console.error(error);
				const job = this.jobs.find(j => j.job_id == job_id);
				
				// Safely extract error message
				let errorMessage = error.message || "Unknown error";
				if ( error.response && error.response.data ) {
					// Check for WP REST API error message format
					if ( error.response.data.message ) {
						errorMessage = error.response.data.message;
					} else if ( typeof error.response.data === 'string' ) {
						errorMessage = error.response.data;
					}
				}

				if (job) {
					job.status = 'error';
					// Push error to the terminal output stream so it's visible in the console window
					if ( job.stream ) {
						job.stream.push("Error: " + errorMessage);
					}
				}

				this.snackbar.message = "Error launching site: " + errorMessage;
				this.snackbar.show = true;
			});
		},
		captureCheck() {
			environment = this.dialog_site.environment_selected.environment
			axios.get( `/wp-json/captaincore/v1/sites/${site.site_id}/${environment}/captures/new`, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.dialog_captures.show = false
				this.snackbar.message = `Generating new capture if site changes detected.`
				this.snackbar.show = true
				
			});
		},
		viewLogs(){
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected
			environment.server_logs.files = []
			environment.view_server_logs = true
			environment.server_log_response = ""
			axios.get(
				`/wp-json/captaincore/v1/sites/${site.site_id}/${environment.environment.toLowerCase()}/logs`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => { 
					environment.server_logs = response.data
					if ( environment.server_logs.files.length > 0 ) {
						environment.server_log_selected = environment.server_logs.files[0].path
						this.fetchLogs()
					}
				});
		},
		fetchLogs() {
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected
			environment.server_log_response = ""
			environment.loading_server_logs = true
			
			// Data to be sent in the POST body
			const postData = {
				file: environment.server_log_selected,
				limit: environment.server_log_limit
			};

			axios.post(
				`/wp-json/captaincore/v1/sites/${site.site_id}/${environment.environment.toLowerCase()}/logs/fetch`, 
				postData, // POST data
				{
					headers: {'X-WP-Nonce':this.wp_nonce}
				}
			)
			.then(response => {
				environment.loading_server_logs = false
				window.Prism = window.Prism || {};
				window.Prism.manual = true;
				environment.server_log_response = Prism.highlight( response.data, Prism.languages.log, 'log')
				Prism.highlightAll()
			});
		},
		showCaptures( site_id ) {
			this.dialog_captures.site = null;
    		this.dialog_captures.captures = [];
			this.dialog_captures.site = this.dialog_site.site
			if ( this.dialog_site.environment_selected.site_id == "" || this.dialog_site.environment_selected.site_id != site_id ) {
				this.fetchSiteEnvironments( site_id )
				return
			}
			environment = this.dialog_site.environment_selected
			this.dialog_captures.pages = environment.capture_pages
			if ( environment.details.auth ) {
				this.dialog_captures.auth = environment.details.auth
			}
			if ( environment.capture_pages == "" || environment.capture_pages == null ) {
				this.dialog_captures.pages = [{ page: "/" }]
			}
			this.dialog_captures.loading = true
			this.dialog_captures.show = true;
			axios.get(
				`/wp-json/captaincore/v1/site/${site_id}/${this.dialog_site.environment_selected.environment.toLowerCase()}/captures`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => { 
					this.dialog_captures.image_path = this.remote_upload_uri + this.dialog_site.site.site + "_" + this.dialog_site.site.site_id + "/" + this.dialog_site.environment_selected.environment.toLowerCase() + "/captures/"
					this.dialog_captures.captures = response.data
					if ( this.dialog_captures.captures.length > 0 ) {
						this.dialog_captures.capture = this.dialog_captures.captures[0]
						this.dialog_captures.selected_page = this.dialog_captures.capture.pages[0]
					}
					this.dialog_captures.loading = false
				});
		},
		switchCapture() {
			this.dialog_captures.image_loading = true;
			this.dialog_captures.selected_page = this.dialog_captures.capture.pages[0]
		},
		closeCaptures() {
			if (this.dialog_site.site.site_id) {
				this.goToPath( `/sites/${this.dialog_site.site.site_id}` )
			}
			this.dialog_captures = { site: {}, auth: { username: "", password: ""}, pages: [{ page: ""}], capture: { pages: [] }, image_path:"", selected_page: "", captures: [], mode: "screenshot", loading: true, show: false, show_configure: false }
		},
		addAdditionalCapturePage() {
			this.dialog_captures.pages.push({ page: "/" });
		},
		updateCaptureConfigurations() {
			site_id = this.dialog_captures.site.site_id
			environment = this.dialog_site.environment_selected.environment
			axios.post( `/wp-json/captaincore/v1/sites/${site_id}/${environment}/captures`, {
				pages: this.dialog_captures.pages,
				auth: this.dialog_captures.auth
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.dialog_captures.show = false;
				this.dialog_captures.pages = [];
				this.dialog_captures.auth = { username: "", password: "" }
			});
		},
		addAdditionalPlan() {
			this.configurations.hosting_plans.push( {"name":"","price":"","limits":{"visits":"","storage":"","sites":""}})
		},
		deletePlan(index) {
			this.configurations.hosting_plans.splice( index, 1 )
		},
		toggleSite( site_id ) {
			site = this.sites.filter( site => site.site_id == site_id )[0]
			this.dialog_toggle.business_name = this.configurations.name
			this.dialog_toggle.business_link = this.configurations.url
			this.dialog_toggle.site_id = site.site_id
			this.dialog_toggle.site_name = site.name
			this.dialog_toggle.show = true
		},
		toggleSiteBulk() {
			sites = this.sites_selected
			site_ids = this.sites_selected.map( s => s.site_id )
			site_name = sites.length + " sites"
			this.dialog_toggle.show = true
			this.dialog_toggle.site_id = site_ids
			this.dialog_toggle.site_name = site_name
			this.dialog_toggle.business_name = this.configurations.name
			this.dialog_toggle.business_link = this.configurations.url
		},
		resetPermissions( site_id ) {
			site = this.dialog_site.site
			site_name =  this.dialog_site.environment_selected.home_url
			site_name = site_name.replace( "https://www.", "" ).replace( "https://", "" ).replace( "http://www.", "" ).replace( "http://", "" )
			
			should_proceed = confirm( `Reset file permissions to defaults on ${site_name}?` )
			description = `Resetting file permissions to defaults on ${site_name}`

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				environment: this.dialog_site.environment_selected.environment,
				post_id: site_id,
				command: 'reset-permissions'
			};
			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					this.runCommand( response.data )
					this.snackbar.message = description;
					this.snackbar.show = true;
				})
				.catch( error => console.log( error ) );
		},
		switchSite() {
			if ( this.selected_site ) {
				this.selected_site.tabs = this.dialog_site.site.tabs
				this.selected_site.tabs_management = this.dialog_site.site.tabs_management
				this.dialog_site.site = this.selected_site
				this.goToPath( `/sites/${this.selected_site.site_id}` )
			}
		},
		grantAccess() {
			axios.post( `/wp-json/captaincore/v1/sites/${this.dialog_site.site.site_id}/grant-access`, {
					account_ids: this.dialog_site.grant_access
				},{
					headers: { 'X-WP-Nonce':this.wp_nonce }
				})
				.then( response => {
					this.snackbar.message = "Access granted successfully"
					this.snackbar.show = true
					this.dialog_site.grant_access = []
					this.fetchSiteDetails( this.dialog_site.site.site_id )
			})
		},
		showSite( site, tab = 'info' ) {
			this.selected_site = site;
			this.users_search = "";

			// Determine the target tab string
			let target_tab_management = 'tab-Info';
			if ( tab === 'backups' || tab === 'quicksaves' || tab === 'snapshots' ) target_tab_management = 'tab-Backups';
			else if ( tab === 'updates' ) target_tab_management = 'tab-Updates';
			else if ( tab === 'scripts' ) target_tab_management = 'tab-Scripts';
			else if ( tab === 'users' ) target_tab_management = 'tab-Users';
			else if ( tab === 'addons' ) target_tab_management = 'tab-Addons';
			else if ( tab === 'logs' ) target_tab_management = 'tab-Logs';
			else if ( tab === 'stats' ) target_tab_management = 'tab-Stats';
			
			if ( this.dialog_site.site.site_id == site.site_id ) {
				this.dialog_site.site.tabs_management = target_tab_management;
				// Still fetch fresh data in background if needed
				this.fetchSiteEnvironments( site.site_id );
				this.fetchSiteDetails( site.site_id );
				return; 
			}

			this.fetchSiteEnvironments( site.site_id )
			this.fetchSiteDetails( site.site_id )
			show_site = JSON.parse ( JSON.stringify ( site ) )
			show_site.usage_breakdown = []
			show_site.pagination = []
			show_site.pagination['sortBy'] = 'roles'
            show_site.users = []
            show_site.update_logs = []
            show_site.timeline = []
			show_site.shared_with = []
            show_site.loading = false
			show_site.tabs = this.dialog_site.site.tabs
			if ( tab === 'backups' ) {
				show_site.tabs_management = 'tab-Backups';
			} else if ( tab === 'quicksaves' ) {
				show_site.tabs_management = 'tab-Backups';
			} else if ( tab === 'snapshots' ) {
				show_site.tabs_management = 'tab-Backups';
			} else if ( tab === 'updates' ) {
				show_site.tabs_management = 'tab-Updates';
			} else if ( tab === 'scripts' ) {
				show_site.tabs_management = 'tab-Scripts';
			} else if ( tab === 'users' ) {
				show_site.tabs_management = 'tab-Users';
			} else if ( tab === 'addons' ) {
				show_site.tabs_management = 'tab-Addons';
			} else if ( tab === 'logs' ) {
				show_site.tabs_management = 'tab-Logs';
			} else if ( tab === 'stats' ) {
				show_site.tabs_management = 'tab-Stats';
			} else if ( tab === 'visual-captures' ) {
				show_site.tabs_management = 'tab-Info'
			} else {
				show_site.tabs_management = 'tab-Info'
			}
			if ( show_site.key == "" ) {
				show_site.key = null
			}
			this.dialog_site.site = show_site
			this.dialog_site.step = 2
			this.dialog_new_site = {
				provider: "kinsta",
				show: false,
				show_vars: false,
				environment_vars: [],
				saving: false,
				domain: "",
				key: "",
				site: "",
				errors: [],
				shared_with: [],
				account_id: "",
				environments: [
					{"environment": "Production", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"",monitor_enabled: "1",updates_enabled: "1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" },
					{"environment": "Staging", "site": "", "address": "","username":"","password":"","protocol":"sftp","port":"2222","home_directory":"",monitor_enabled: "0",updates_enabled: "1","offload_enabled": false,"offload_provider":"","offload_access_key":"","offload_secret_key":"","offload_bucket":"","offload_path":"" }
				],
			}
		},
		copySSH( site ) {
			axios.get( `/wp-json/captaincore/v1/sites/${site.site_id}/environments`, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.copyText( response.data[0].ssh )
			});
		},
		fetchPHPmyadmin(){
			site_id = this.dialog_site.site.site_id
			environment = this.dialog_site.environment_selected.environment.toLowerCase()
			this.snackbar.message = "Opening PHPMyAdmin for " + this.dialog_site.environment_selected.home_url
			this.snackbar.show = true
			axios.get(
				`/wp-json/captaincore/v1/sites/${site_id}/${environment}/phpmyadmin`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					window.open( response.data )
				});
		},
		scanErrors( site ) {
			site.loading = true

			var data = {
				action: 'captaincore_install',
				post_id: site.site_id,
				command: 'scan-errors',
			};

			description = "Scanning " + site.name + " for errors";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({ "job_id": job_id, "description": description, "status": "queued", stream: [], "command": "scanErrors", "site_id": site.site_id });

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with responsed background job id
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					this.runCommand( response.data );
				})
				.catch( error => console.log( error ) );
		},
		showSiteMigration( site_id ){
			site = this.dialog_site.site
			this.dialog_migration.sites.push( site );
			this.dialog_migration.show = true;
			this.dialog_migration.site_id = site.site_id
			this.dialog_migration.site_name = site.name;
		},
		validateSiteMigration() {
			if ( this.$refs.formSiteMigration.validate() ) {
				this.siteMigration( this.dialog_migration.site_id );
			}	
		},
		siteMigration( site_id ) {
			site = this.dialog_site.site
			site_name = site.name;

			should_proceed = confirm("Migrate from backup url? This will overwrite the existing site at " + site_name + ".");
			description = "Migrating backup to '" + site_name + "'";

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				command: 'migrate',
				value: this.dialog_migration.backup_url,
				update_urls: this.dialog_migration.update_urls,
				environment: this.dialog_site.environment_selected.environment
			};

			self = this;
			description = "Migrating backup to '" + site_name + "'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", "command": "migrate", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
			.then( response => {
				self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
				self.runCommand( response.data )
				self.snackbar.message = "Migration backup to " + site_name;
				self.snackbar.show = true;
				self.dialog_migration.show = false;
				self.dialog_migration.sites = [];
				self.dialog_migration.backup_url = "";
				self.dialog_migration.update_urls = "";
			})
			.catch( error => console.log( error ) );

		},
		DeactivateSite( site_id ) {

			site = this.dialog_site.site
			site_name = this.dialog_toggle.site_name;

			if ( Array.isArray( site_id ) ) { 
				environment = this.dialog_bulk_tools.environment_selected
			} else {
				environment = this.dialog_site.environment_selected.environment
			}

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				command: 'deactivate',
				environment: environment,
				name: this.dialog_toggle.business_name,
				link: this.dialog_toggle.business_link
			};

			self = this;
			description = "Deactivating '" + site_name + "'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
					self.snackbar.message = "Deactivating " + site_name;
					self.snackbar.show = true;
					self.dialog_toggle.show = false;
					self.dialog_toggle.site_id = "";
					self.dialog_toggle.site_name = "";
					self.dialog_toggle.business_name = "";
					self.dialog_toggle.business_link = "";
				})
				.catch( error => console.log( error ) );

		},
		ActivateSite( site_id ) {

			site = this.dialog_site.site
			site_name = this.dialog_toggle.site_name;

			if ( Array.isArray( site_id ) ) { 
				environment = this.dialog_bulk_tools.environment_selected
			} else {
				environment = this.dialog_site.environment_selected.environment
			}

			var data = {
				action: 'captaincore_install',
				post_id: site_id,
				environment: environment,
				command: 'activate'
			};

			self = this;
			description = "Activating '" + site_name + "'";

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data )
					self.snackbar.message = "Activating " + site_name;
					self.snackbar.show = true;
					self.dialog_toggle.show = false;
					self.dialog_toggle.site_id = "";
					self.dialog_toggle.site_name = "";
					self.dialog_toggle.business_name = "";
					self.dialog_toggle.business_link = "";
				})
				.catch( error => console.log( error ) );

		},
		siteDeploy( site_id ) {

			site = this.dialog_site.site
			site_name =  this.dialog_site.environment_selected.home_url
			site_name = site_name.replace( "https://www.", "" ).replace( "https://", "" ).replace( "http://www.", "" ).replace( "http://", "" )
			
			should_proceed = confirm( `Deploy defaults on ${site_name}?` )
			description = `Deploy defaults on ${site_name}`

			if ( ! should_proceed ) {
				return
			}

			var data = {
				action: 'captaincore_install',
				environment: this.dialog_site.environment_selected.environment,
				post_id: site_id,
				command: 'deploy-defaults'
			};

			// Start job
			job_id = Math.round((new Date()).getTime())
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []})

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data
					this.runCommand( response.data )
					this.snackbar.message = description
					this.snackbar.show = true
				})
				.catch( error => console.log( error ) );

		},
		siteDeployBulk(){

			sites = this.sites_selected
			site_ids = sites.map( s => s.site_id )
			should_proceed = confirm("Deploy defaults on " + sites.length + " sites?")
			description = "Deploying defaults on '" + sites.length + " sites'"

			if ( ! should_proceed ) {
				return;
			}

			var data = {
				action: 'captaincore_install',
				environment: this.dialog_bulk_tools.environment_selected,
				post_id: site_ids,
				command: 'deploy-defaults'
			}

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id ,"site_id": site_ids, "command": "manage", "description": description, "status": "queued", stream: []})

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data
					this.runCommand( response.data )
					this.snackbar.message = description
					this.snackbar.show = true
				})
				.catch( error => console.log( error ) )

		},
		deleteScript( script_id ) {
			should_proceed = confirm( `Delete script?` )

			if ( ! should_proceed ) {
				return
			}
			axios.delete( `/wp-json/captaincore/v1/scripts/${script_id}`, {
					headers: { 'X-WP-Nonce':this.wp_nonce }
				})
				.then( response => {
					this.snackbar.message = `Deleted code to run on ${this.dialog_site.environment_selected.home_url} at ${this.dialog_edit_script.script.run_at_time} ${this.dialog_edit_script.script.run_at_date}.`
					this.snackbar.show = true
					this.script.code = "";
					this.script.menu = false;
					this.script.menu_date = false;
					this.script.menu_time = false;
					this.script.time = "";
					this.script.date = "";
					this.dialog_edit_script.show = false;
					this.dialog_edit_script.script = { script_id: "", code: "", run_at_time: "", run_at_date: "" }
					this.fetchSiteEnvironments( this.dialog_site.site.site_id )
				});
		},
		runCustomCode( site_id ) {
			const site = this.dialog_site.site;
			const env = this.dialog_site.environment_selected;
			
			let should_proceed = confirm("Deploy custom code on " + env.home_url + "?");

			if ( ! should_proceed ) {
				return;
			}

			const description = "Deploying custom code on " + env.home_url;
			const jobId = Math.round((new Date()).getTime());
			
			this.jobs.push({
				"job_id": jobId, 
				"description": description, 
				"status": "queued", 
				"stream": []
			});

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: [env.environment_id],
				code: this.script.code
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand( response.data );
				}
				this.script.code = "";
			})
			.catch( error => console.log( error ) );
		},
		runCustomCodeBulkEnvironments( environments ) {
			should_proceed = confirm( `Deploy custom code on ${environments.length} environments?` );
			if ( ! should_proceed ) {
				return;
			}
			wp_cli = this.script.code;

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id, "description": description, "status": "queued", stream: []});

			axios.post( `/wp-json/captaincore/v1/run/code`, {
					environments: environments.map( environment => environment.enviroment_id ),
					code: this.script.code
				}, {
					headers: { 'X-WP-Nonce':this.wp_nonce }
				})
			.then( response => {
				this.snackbar.message = `Running custom code on ${environments.length} environments.`
				this.snackbar.show = true
				this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
				this.runCommand( response.data )
				this.script.code = "";
			});
		},
		fetchTimeline( site_id ) {
			var data = {
				action: 'captaincore_ajax',
				post_id: site_id,
				command: 'timeline'
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_site.site.timeline = response.data
				})
				.catch( error => console.log( error ) );
		},
		addEnvironmentVarNewSite() {
			this.dialog_new_site.environment_vars.push({ key: '', value: '' })
		},
		removeEnvironmentVarNewSite( index ) {
			this.dialog_new_site.environment_vars.splice( index, 1 )
		},
		addEnvironmentVar() {
			this.dialog_edit_site.site.environment_vars.push({ key: '', value: '' })
		},
		removeEnvironmentVar( index ) {
			this.dialog_edit_site.site.environment_vars.splice( index, 1 )
		},
		addDefaultsUser() {
			this.dialog_account.records.account.defaults.users.push({ email: "", first_name: "", last_name: "", role: "administrator", username: "" })
		},
		addGlobalDefaultsUser() {
			this.defaults.users.push({ email: "", first_name: "", last_name: "", role: "administrator", username: "" })
		},
		addProvider() {
			axios.post( '/wp-json/captaincore/v1/providers', {
				provider: this.dialog_new_provider.provider
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				if ( response.data.errors ) {
					this.dialog_new_provider.loading = false
					this.dialog_new_provider.errors = response.data.errors
					return
				}
				this.snackbar.message = `Provider ${this.dialog_new_provider.provider.name} has been added.`
				this.snackbar.show = true
				this.dialog_new_provider = { show: false, provider: { name: "", provider: "", credentials: [ { "name": "", "value": "" } ] }, loading: false, errors: [] }
				this.fetchProviders()
			});
		},
		editProvider( provider ) {
			this.dialog_edit_provider.provider = provider
			this.dialog_edit_provider.show = true
		},
		updateProvider() {
			provider_id = this.dialog_edit_provider.provider.provider_id
			axios.put( `/wp-json/captaincore/v1/providers/${provider_id}`, {
				provider: this.dialog_edit_provider.provider
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				if ( response.data.errors ) {
					this.dialog_edit_provider.loading = false
					this.dialog_edit_provider.errors = response.data.errors
					return
				}
				this.snackbar.message = `Provider ${this.dialog_edit_provider.provider.name} has been updated.`
				this.snackbar.show = true
				this.dialog_edit_provider = { show: false, provider: { name: "", provider: "", credentials: [ { "name": "", "value": "" } ] }, loading: false, errors: [] }
				this.fetchProviders()
			});
		},
		deleteProvider() {
			should_proceed = confirm("Delete provider " +  this.dialog_edit_provider.provider.name + "?");
			if ( ! should_proceed ) {
				return;
			}
			provider_id = this.dialog_edit_provider.provider.provider_id
			axios.delete( `/wp-json/captaincore/v1/providers/${provider_id}`, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				if ( response.data.errors ) {
					this.dialog_edit_provider.loading = false
					this.dialog_edit_provider.errors = response.data.errors
					return
				}
				this.snackbar.message = `Provider ${this.dialog_edit_provider.provider.name} has been deleted.`
				this.snackbar.show = true
				this.dialog_edit_provider = { show: false, provider: { name: "", provider: "", credentials: [ { "name": "", "value": "" } ] }, loading: false, errors: [] }
				this.fetchProviders()
			});
		},
		addDomain() {
			this.dialog_new_domain.loading = true;
			this.dialog_new_domain.errors  = [];

			var data = {
				action: 'captaincore_account',
				command: 'addDomain',
				value: this.dialog_new_domain.domain.name,
				account_id: this.dialog_new_domain.domain.account_id,
				create_dns_zone: this.dialog_new_domain.domain.create_dns_zone
			};

			// If user is not admin, send site_id instead
			if (this.role != 'administrator') {
				data.site_id = this.dialog_new_domain.domain.site_id;
				delete data.account_id; // Remove account_id if not admin
			}

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// If error then response
					if ( response.data.errors ) {
						this.dialog_new_domain.loading = false
						this.dialog_new_domain.errors = response.data.errors;
						return;
					}
					this.dialog_new_domain.loading = false;
					this.dialog_new_domain = { show: false, domain: { name: "", account_id: "", site_id: "", create_dns_zone: true }, loading: false, errors: [] };
					this.domains.push( response.data )
					this.domains.sort((a, b) => (a.name > b.name) ? 1 : -1)
					this.snackbar.message = "Added new domain " + response.data.name;
					this.snackbar.show = true;
				})
				.catch( error => {
					this.snackbar.message = error;
					this.snackbar.show = true;
					this.dialog_new_domain.loading = false;
				});
		},
		updateDomainSiteLink() {
			if (!this.dialog_domain.update_account.site) {
				this.snackbar.message = "Please select a site.";
				this.snackbar.show = true;
				return;
			}
			
			const selectedSite = this.dialog_domain.update_account.site;
			const domain_id = this.dialog_domain.domain.domain_id;
			const site_id = selectedSite.site_id; // Get the site_id
			
			if (!site_id || site_id == "0") {
				this.snackbar.message = "The selected site is not valid.";
				this.snackbar.show = true;
				return;
			}

			axios.post(`/wp-json/captaincore/v1/domain/${domain_id}/update-site-link`, {
				site_id: site_id
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				this.snackbar.message = response.data.message || "Domain billing account updated successfully."; // Use message from REST response
				this.snackbar.show = true;
				this.dialog_domain.update_account.show = false;
				this.dialog_domain.update_account.site = null;
				
				// Re-fetch domain data to reflect changes
				this.fetchDomain( this.dialog_domain.domain ); 
			})
			.catch( error => {
				this.snackbar.message = "An error occurred: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
			});
		},
		addRecord() {
			timestamp = new Date().getTime();
			this.dialog_domain.records.push({ id: "new_" + timestamp, edit: false, delete: false, new: true, ttl: "3600", type: "A", value: [{"value": "","enabled":true}], update: {"record_id": "new_" + timestamp, "record_type": "A", "record_name": "", "record_value": [{ value: "", enabled: true }], "record_ttl": "3600", "record_status": "new-record" } });
		},
		addRecordValue( index ) {
			record = this.dialog_domain.records[index];
			if ( record.type == "A" || record.type == "AAAA" || record.type == "ANAME" || record.type == "TXT" || record.type == "SPF" ) {
				record.update.record_value.push({ value: "", enabled: true });
			}
			if ( record.type == "MX" ) {
				record.update.record_value.push({ priority: "", server: "", enabled: true });
			}
			if ( record.type == "SRV" ) {
				record.update.record_value.push({ priority: 100, weight: 1, port: 443, host: "", enabled: true });
			}
		},
		viewRecord( record_id ){
			record = this.dialog_domain.records.filter( r => r.id == record_id )[0];
			record.edit = false
			record.delete = false
		},
		editRecord( record_id ){
			record = this.dialog_domain.records.filter( r => r.id == record_id )[0];
			record.edit = true
			record.delete = false
		},
		changeRecordType( index ) {
			record = this.dialog_domain.records.filter( (r, i) => i == index )[0];
			if ( record.type == "A" || record.type == "AAAA" || record.type == "CNAME" || record.type == "ANAME" || record.type == "TXT" || record.type == "SPF" ) {
				record.update.record_value = [{ value: "", enabled: true }];
			}
			if ( record.type == "MX" ) {
				record.update.record_value = [{ priority: "", server: "", enabled: true }];
			}
			if ( record.type == "SRV" ) {
				record.update.record_value = [{ priority: 100, weight: 1, port: 443, host: "", enabled: true }];
			}
			if ( record.type == "HTTP" ) {
				record.update.record_value = [{ url: "", redirectType: "301" }];
			}
		},
		deleteUserValue( delete_index ) {
			this.dialog_account.records.account.defaults.users = this.dialog_account.records.account.defaults.users.filter( (u, index) => index != delete_index )
		},
		deleteGlobalUserValue( delete_index ) {
			this.defaults.users = this.defaults.users.filter( (u, index) => index != delete_index )
		},
		deleteRecordValue( index, value_index ) {
			this.dialog_domain.records[index].update.record_value.splice( value_index, 1 )
		},
		deleteCurrentRecord( record_id ){
			record = this.dialog_domain.records.filter( r => r.id == record_id )[0];
			record.edit = false
			record.delete = !record.delete
		},
		deleteRecord( index ){
			this.dialog_domain.records.splice( index, 1 )
		},
		updateDomainContacts() {
			this.dialog_domain.updating_contacts = true
			axios.post( `/wp-json/captaincore/v1/domain/${domain.domain_id}/contacts`, {
					'contacts': this.dialog_domain.provider.contacts
				},{
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then( response => {
					if ( response.data.error ) {
						this.dialog_domain.updating_contacts = false
						this.snackbar.message = response.data.error
						this.snackbar.show = true
						this.dialog_domain.loading = false
						return
					}
					this.dialog_domain.updating_contacts = false
					this.snackbar.message = response.data.response
					this.snackbar.show = true
					this.dialog_domain.loading = false
				})
				.catch( error => {
					this.dialog_domain.updating_contacts = false
					this.snackbar.message = error
					this.snackbar.show = true
					this.dialog_domain.loading = false
				})
		},
		updateDomainNameservers() {
			this.dialog_domain.updating_nameservers = true
			axios.post( `/wp-json/captaincore/v1/domain/${domain.domain_id}/nameservers`, {
					'nameservers': this.dialog_domain.provider.nameservers.map( ns => ns.value )
				},{
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then( response => {
					if ( response.data.error ) {
						this.dialog_domain.updating_nameservers = false
						this.snackbar.message = response.data.error
						this.snackbar.show = true
						this.dialog_domain.loading = false
						return
					}
					this.dialog_domain.updating_nameservers = false
					this.snackbar.message = response.data.response
					this.snackbar.show = true
					this.dialog_domain.loading = false
				})
				.catch( error => {
					this.dialog_domain.updating_nameservers = false
					this.snackbar.message = error
					this.snackbar.show = true
					this.dialog_domain.loading = false
				})
		},
		domainLockUpdate() {
			this.dialog_domain.update_lock = true
			status = this.dialog_domain.provider.locked
			axios.get(
				`/wp-json/captaincore/v1/domain/${domain.domain_id}/lock_${status}`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.snackbar.message = `Domain lock has been turned ${status}.`
					this.snackbar.show = true
					this.dialog_domain.update_lock = false
				})
		},
		domainPrivacyUpdate() {
			this.dialog_domain.update_privacy = true
			status = this.dialog_domain.provider.whois_privacy
			axios.get(
				`/wp-json/captaincore/v1/domain/${domain.domain_id}/privacy_${status}`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.snackbar.message = `Domain privacy has been turned ${status}.`
					this.snackbar.show = true
					this.dialog_domain.update_privacy = false
				})
		},
		retrieveAuthCode() {
			this.dialog_domain.fetch_auth_code = true
			axios.get(
				`/wp-json/captaincore/v1/domain/${domain.domain_id}/auth_code`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.dialog_domain.auth_code = response.data
					this.dialog_domain.fetch_auth_code = false
					if ( response.data == "" ) {
						this.snackbar.message = "Failed to retrieve auth code."
						this.snackbar.show = true
						return
					}
				})
		},
		fetchDomain( domain, tab = 'dns' ) {
			if ( this.role == "administrator" ) {
				this.fetchProviders()
			}
			return axios.get(
				'/wp-json/captaincore/v1/domain/' + domain.domain_id, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.dialog_domain.accounts = response.data.accounts
					this.dialog_domain.account_ids = response.data.accounts.map( a => a.account_id )
					this.dialog_domain.provider_id = response.data.provider_id
					this.dialog_domain.connected_sites = response.data.connected_sites
					this.dialog_domain.details = response.data.details || {}
					if ( response.data.provider.errors ) {
						this.dialog_domain.provider = { contacts: {} }
					} else {
						this.dialog_domain.provider = response.data.provider
					}
					if ( this.dialog_domain.provider.contacts.owner && this.dialog_domain.provider.contacts.owner.country && this.dialog_domain.provider.contacts.owner.country != "" ) {
						this.populateStatesFor( this.dialog_domain.provider.contacts.owner )
					}
					if (this.dialog_domain.details.forward_email_id) {
						this.fetchEmailForwards();
					}
					if (this.dialog_domain.details.mailgun_id) {
						this.fetchMailgunDetails();
					}
				})
				.finally(() => {
					this.dialog_domain.loading = false;
					this.dialog_domain.tabs = tab;
				});
		},
		activateEmailForwarding( overwrite = false ) {
			this.dialog_domain.activating_forwarding = true;
			// Close the confirmation dialog if it was open
			if (overwrite) {
				this.dialog_domain.confirm_mx_overwrite = false;
			}
			
			const domain_id = this.dialog_domain.domain.domain_id;
			axios.post(`/wp-json/captaincore/v1/domain/${domain_id}/activate-forward-email`, {
				overwrite_mx: overwrite // Pass the overwrite flag
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.dialog_domain.activating_forwarding = false;
				if (response.data.id) {
					this.snackbar.message = "Email forwarding successfully activated. DNS records are being added.";
					this.snackbar.show = true;
					this.dialog_domain.details.forward_email_id = response.data.id;
					// Switch to the new tab and load the forwards
					this.dialog_domain.tabs = "email-forwarding";
					this.fetchEmailForwards();
				} else {
					this.snackbar.message = "Error: " + (response.data.message || "Could not activate forwarding.");
					this.snackbar.show = true;
				}
			})
			.catch(error => {
				this.dialog_domain.activating_forwarding = false;
				// Check for the specific conflict error
				if (error.response && error.response.data && error.response.data.code === 'mx_conflict') {
					// Open the confirmation dialog
					this.dialog_domain.confirm_mx_overwrite = true;
				} else {
					// Show any other errors normally
					this.snackbar.message = "Error: " + (error.response?.data?.message || error.message);
					this.snackbar.show = true;
				}
			});
		},
		verifyForwardEmailDns( domain_id ) {
			this.dialog_domain.forwards_domain.loading = true;
			axios.get(
				`/wp-json/captaincore/v1/domain/${domain_id}/email-forwarding/status?verify=true`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.dialog_domain.forwards_domain.data = response.data;
					this.dialog_domain.forwards_domain.loading = false;
					if (response.data.has_mx_record && response.data.has_txt_record) {
						this.snackbar.message = "Domain verified successfully!";
					} else {
						this.snackbar.message = "Verification check complete. Domain is not yet verified.";
					}
					this.snackbar.show = true;
				})
				.catch(error => {
					this.snackbar.message = "Error checking verification: " + (error.response?.data?.message || error.message);
					this.snackbar.show = true;
					this.dialog_domain.forwards_domain.loading = false;
				});
		},
		fetchEmailForwards() {
			this.dialog_domain.forwards.loading = true;
			this.dialog_domain.forwards_domain.loading = true; // Also set domain loading
			const domain_id = this.dialog_domain.domain.domain_id;

			// Fetch Aliases
			axios.get(`/wp-json/captaincore/v1/domain/${domain_id}/email-forwards`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.dialog_domain.forwards.items = response.data.map(alias => {
					// Convert recipients array to a comma-separated string for editing
					alias.recipients_string = alias.recipients.join(', ');
					return alias;
				});
				this.dialog_domain.forwards.loading = false;
			})
			.catch(error => {
				this.snackbar.message = "Error fetching email forwards: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
				this.dialog_domain.forwards.loading = false;
			});

			// Also Fetch Domain Verification Status
			axios.get(
				`/wp-json/captaincore/v1/domain/${domain_id}/email-forwarding/status`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.dialog_domain.forwards_domain.data = response.data;
					this.dialog_domain.forwards_domain.loading = false;
				})
				.catch(error => {
					this.snackbar.message = "Error fetching domain status: " + (error.response?.data?.message || error.message);
					this.snackbar.show = true;
					this.dialog_domain.forwards_domain.loading = false;
				});
		},
		addEmailForward() {
			this.dialog_domain.forwards.edited_index = -1;
			this.dialog_domain.forwards.edited_item = { name: '', recipients_string: '', is_enabled: true };
			this.dialog_domain.forwards.show_dialog = true;
		},
		editEmailForward(item) {
			this.dialog_domain.forwards.edited_index = this.dialog_domain.forwards.items.indexOf(item);
			this.dialog_domain.forwards.edited_item = { ...item }; // Clone item for editing
			this.dialog_domain.forwards.show_dialog = true;
		},
		saveEmailForward() {
			this.dialog_domain.forwards.loading = true;
			const domain_id = this.dialog_domain.domain.domain_id;
			const item = this.dialog_domain.forwards.edited_item;
			
			// Convert comma-separated string back to array of recipients
			const payload = {
				name: item.name,
				is_enabled: item.is_enabled,
				recipients: item.recipients_string.split(',').map(email => email.trim()).filter(email => email),
			};

			let apiCall;
			if (this.dialog_domain.forwards.edited_index > -1) {
				// Update existing
				const alias_id = item.id;
				apiCall = axios.put(`/wp-json/captaincore/v1/domain/${domain_id}/email-forwards/${alias_id}`, payload, {
					headers: { 'X-WP-Nonce': this.wp_nonce }
				});
			} else {
				// Create new
				apiCall = axios.post(`/wp-json/captaincore/v1/domain/${domain_id}/email-forwards`, payload, {
					headers: { 'X-WP-Nonce': this.wp_nonce }
				});
			}

			apiCall.then(response => {
				this.snackbar.message = "Email forward saved successfully.";
				this.snackbar.show = true;
				this.closeEmailForwardDialog();
				this.fetchEmailForwards(); // Refresh list
			})
			.catch(error => {
				this.snackbar.message = "Error: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
				this.dialog_domain.forwards.loading = false;
			});
		},
		deleteEmailForward(item) {
			if (!confirm(`Are you sure you want to delete the alias "${item.name}@${this.dialog_domain.domain.name}"?`)) {
				return;
			}
			this.dialog_domain.forwards.loading = true;
			const domain_id = this.dialog_domain.domain.domain_id;
			const alias_id = item.id;

			axios.delete(`/wp-json/captaincore/v1/domain/${domain_id}/email-forwards/${alias_id}`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.snackbar.message = "Email forward deleted successfully.";
				this.snackbar.show = true;
				this.fetchEmailForwards(); // Refresh list
			})
			.catch(error => {
				this.snackbar.message = "Error deleting: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
				this.dialog_domain.forwards.loading = false;
			});
		},
		closeEmailForwardDialog() {
			this.dialog_domain.forwards.show_dialog = false;
			this.$nextTick(() => {
				this.dialog_domain.forwards.edited_item = { name: '', recipients: '', is_enabled: true };
				this.dialog_domain.forwards.edited_index = -1;
			});
		},
		inlineUpdateEmailForward(item, is_enabled) {
			const domain_id = this.dialog_domain.domain.domain_id;
			const alias_id = item.id;

			// The API PUT endpoint requires the full object, not just the changed field.
			// We build the payload from the 'item' in the table row.
			const payload = {
				name: item.name,
				is_enabled: is_enabled,
				recipients: item.recipients_string.split(',').map(email => email.trim()).filter(email => email),
			};
			
			this.dialog_domain.forwards.loading = true;

			axios.put(`/wp-json/captaincore/v1/domain/${domain_id}/email-forwards/${alias_id}`, payload, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.snackbar.message = `Forward '${item.name}' ${is_enabled ? 'enabled' : 'disabled'}.`;
				this.snackbar.show = true;
				// v-model already updated the item, so we just stop loading.
				this.dialog_domain.forwards.loading = false;
			})
			.catch(error => {
				this.snackbar.message = "Error: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
				// Revert the switch on error
				item.is_enabled = !is_enabled; 
				this.dialog_domain.forwards.loading = false;
			});
		},
		modifyDNS( domain ) {
			this.dialog_domain = {
				show: false,
				updating_contacts: false,
				updating_nameservers: false,
				auth_code: "",
				fetch_auth_code: false,
				provider: { contacts: {} },
				contact_tabs: "",
				tabs: "dns",
				show_import: false,
				import_json: "",
				domain: {},
				records: [],
				nameservers: [],
				results: [],
				errors: [],
				info: [],
				loading: true,
				saving: false,
				step: 2,
				details: {},
				activating_forwarding: false,
				confirm_mx_overwrite: false,
				deleting_dns_zone: false,
				confirm_delete_dns_zone: false,
				forwards_domain: { loading: false, data: null }, 
				forwards: {
					loading: false,
					items: [],
					show_dialog: false,
					edited_item: { name: '', recipients: '', is_enabled: true },
					edited_index: -1,
				},
				update_account: { show: false, site: null }
			};
			if ( domain.remote_id == null ) {
				this.dialog_domain.info = [ "DNS zone is not active. Activate it to manage DNS records." ];
				this.dialog_domain.domain = domain;
				this.dialog_domain.loading = false
				this.dialog_domain.show = true;
				return
			}
			axios.get(
				'/wp-json/captaincore/v1/dns/' + domain.domain_id, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					if ( typeof response.data == "string" ) {
						this.dialog_domain.errors = [ response.data ];
						this.dialog_domain.loading = false
						return
					}

					if ( typeof response.data.errors == 'object' ) {
						this.dialog_domain.loading = false
						this.dialog_domain.errors = response.data.errors
						return
					}

					const records = response.data.records || [];

					if ( response.data.records == null ) {
						this.dialog_domain.loading = false
						this.dialog_domain.domain.remote_id = null
						this.dialog_domain.info = [ "DNS zone is not active. Activate it to manage DNS records." ];
						return
					}

					// Prep records with 
					records.forEach( r => {
						r.update = {
							"record_id": JSON.parse(JSON.stringify(r.id)),
							"record_type": JSON.parse(JSON.stringify(r.type)),
							"record_name": JSON.parse(JSON.stringify(r.name)),
							"record_value": JSON.parse(JSON.stringify(r.value)),
							"record_ttl": JSON.parse(JSON.stringify(r.ttl)),
							"record_status": "edit-record"
						};
						r.edit = false;
						r.delete = false;
					});
					timestamp = new Date().getTime();
					records.push({ id: "new_" + timestamp, edit: false, delete: false, new: true, ttl: "3600", type: "A", value: [{"value": "","enabled":true}], update: {"record_id": "new_" + timestamp, "record_type": "A", "record_name": "", "record_value": [{"value": "","enabled":true}], "record_ttl": "3600", "record_status": "new-record" } });
					this.dialog_domain.records = records
					this.dialog_domain.nameservers = response.data.nameservers
				});
			this.dialog_domain.domain = domain;
			this.dialog_domain.show = true;
		},
		loadDNSRecords() {
			axios.post(
				`/wp-json/captaincore/v1/domains/import`, {
					'domain': this.dialog_domain.domain.name,
					'zone': this.dialog_domain.import_json
				},{
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					import_json = response.data
					// Remove any pending new records
					this.dialog_domain.records = this.dialog_domain.records.filter( record => ! record.new )
					// Mark existing records to be deleted
					this.dialog_domain.records.forEach( record => {
						record.delete = true
					})
					// Process records to be imported and mark as new
					index = 0
					import_json.forEach( record => {
						if ( record.type == "SOA" || record.type == "NS" ) {
							return;
						}
						timestamp = new Date().getTime();
						key ="new_" + timestamp + "_" + index
						item = { id: key, edit: false, delete: false, new: true, ttl: "3600", type: "A", value: [{"value": "","enabled":true}], update: {"record_id": key, "record_type": "A", "record_name": "", "record_value": [{ value: "", enabled: true }], "record_ttl": "3600", "record_status": "new-record" } };
						item.type = record.type
						item.update.record_name = record.name
						item.update.record_type = record.type
						item.update.record_value = [{ value: record.value, enabled: true }]
						if ( record.type == "MX" ) {
							value = record.value.split(" ")
							item.update.record_value = [{ priority: value[0], server: value[1], enabled: true }]
						}
						if ( record.type == "SRV" ) {
							value = record.value.split(" ")
							item.update.record_value = [{ priority: value[0], weight: value[1], port: value[2], host: value[3], enabled: true }]
						}
						this.dialog_domain.records.push( item )
						index++
					})
					this.dialog_domain.import_json = ""
					this.dialog_domain.show_import = false
					this.groupDNS()
					this.addRecord()
					this.snackbar.message = "Loaded DNS records from import. Review then save records."
					this.snackbar.show = true
				})
		},
		exportDomain() {
			axios.get(
				`/wp-json/captaincore/v1/domains/${this.dialog_domain.domain.domain_id}/zone`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.$refs.export_domain.download = `${this.dialog_domain.domain.name}.txt`;
					this.$refs.export_domain.href = "data:text/json;charset=utf-8," + encodeURIComponent(response.data);
					this.$refs.export_domain.click();
				})
			
		},
		activateDNSZone(domain) {
			this.dialog_domain.deleting_dns_zone = true;
			axios.post(`/wp-json/captaincore/v1/domain/${domain.domain_id}/activate-dns-zone`, {}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.snackbar.message = response.data.message || "DNS zone activated.";
				this.snackbar.show = true;
				// Update the local domain object with the new remote_id
				this.dialog_domain.domain.remote_id = response.data.remote_id;
				// Manually refresh DNS records tab since it's now active
				this.modifyDNS(this.dialog_domain.domain);
				this.fetchDomain(this.dialog_domain.domain);
			})
			.catch(error => {
				this.snackbar.message = "Error: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
			})
			.finally(() => {
				this.dialog_domain.deleting_dns_zone = false;
			});
		},
		deleteDNSZone(domain) {
			this.dialog_domain.deleting_dns_zone = true;
			this.dialog_domain.confirm_delete_dns_zone = false;

			axios.delete(`/wp-json/captaincore/v1/domain/${domain.domain_id}/dns-zone`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.snackbar.message = response.data.message || "DNS zone deleted.";
				this.snackbar.show = true;
				// Clear the remote_id locally
				this.dialog_domain.domain.remote_id = null;
				// Clear the records list as the zone is gone
				this.dialog_domain.records = [];
				this.dialog_domain.info = [ "DNS zone is not active. Activate it to manage DNS records." ];
			})
			.catch(error => {
				this.snackbar.message = "Error: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
			})
			.finally(() => {
				this.dialog_domain.deleting_dns_zone = false;
			});
		},
		deleteDomain() {
			should_proceed = confirm("Delete domain " +  this.dialog_domain.domain.name + "? All DNS records will be removed and domain will be removed.");
			if ( ! should_proceed ) {
				return;
			}
			this.dialog_domain.loading = true
			var data = {
				action: 'captaincore_account',
				command: 'deleteDomain',
				value: this.dialog_domain.domain.domain_id,
				account: this.dialog_domain.domain.account_id
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Set loading to false
					this.dialog_domain.loading = false;
					// Remove the domain from the local array
					this.domains = this.domains.filter( d => d.domain_id != response.data.domain_id );
					// Navigate back to the domains list (this will set dialog_domain.step = 1)
					this.goToPath( '/domains' );
					// Show snackbar
					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				})
				.catch( error => {
					this.snackbar.message = error
					this.snackbar.show = true
					this.dialog_domain.loading = false
				});
		},
		groupDNS() {
			records_to_check = this.dialog_domain.records.filter( record => ! record.delete && record.type != "CNAME" && record.type != "HTTP" && record.update.record_value[0].value != "" )
			records_to_check.forEach( record => {
				records_to_compare = records_to_check.filter( item => item.id != record.id && item.update.record_name == record.update.record_name && item.type == record.type && ! item.merged )
				if ( records_to_compare.length > 0 ) {
					record.edit = true
					record.merged = true
					records_to_compare.forEach( duplicate => {
						record.update.record_value = record.update.record_value.concat( duplicate.update.record_value )
						this.dialog_domain.records = this.dialog_domain.records.filter( r => r.id != duplicate.id )
					})
					//console.log(record.id + " has " + records_to_compare.length + " conflicting records")
				}
			})
		},
		saveDNS() {
			this.groupDNS()
			this.dialog_domain.saving = true;
			domain_id = this.dialog_domain.domain.remote_id;
			record_updates = []

			// Warn if domain is included in DNS entries
			record_warnings = []
			this.dialog_domain.records.forEach( record => {
				if ( record.edit || record.new && ( record.type == "CNAME" && record.update.record_name.includes(this.dialog_domain.domain.name) ) ) {
					record_warnings.push( record )
				}
			})
			/* if ( this.dialog_domain.ignore_warnings != true && record_warnings != "" ) {
				this.snackbar.message = "Show domain warnings."
				this.snackbar.show = true
				this.dialog_domain.saving = false
				return;
			} */

			this.dialog_domain.records.forEach( record => {
				// Format value for API
				if ( record.type != "HTTP" ) {
					record_value = [];
					record.update.record_value.forEach( v => {
						if ( ! v.value  ) {
							return
						}
						if ( v.value.value  ) {
							v.value.value = v.value.value.trim()
							record_value.push( v )
							return
						}
						v.value = v.value.trim()
						if ( record.type == "CNAME" || record.type == "ANAME" ) {
							// Check for value ending in period. If not add one.
							if ( v.value.substr(v.value.length - 1) != "." ) {
								v.value = v.value + ".";
							}
						}
						record_value.push( v )
					});
				}

				if ( record.type == "MX" ) {
					// Check for value ending in period. If not add one.
					record.update.record_value.forEach( v => {
						v.server = v.server.trim();
						if ( v.server.substr(v.server.length - 1) != "." ) {
							v.server = v.server + ".";
						}
					})
					record_value = record.update.record_value
				}

				if ( record.type == "SRV" ) {
					// Check for value ending in period. If not add one.
					record.update.record_value.forEach( v => {
						v.host = v.host.trim();
						if ( v.host.substr(v.host.length - 1) != "." ) {
							v.host = v.host + ".";
						}
					})
					record_value = record.update.record_value
				}

				if ( record.type == "TXT" ) {
					if ( record.update.record_name == "@" ) {
						record.update.record_name = ""
					}
					// Check for value wrapped in quotes. If not add them.
					record.update.record_value.forEach( v => {
						v.value = v.value.trim();
						if ( v.value.substr(0,1) != '"' ) {
							v.value = '"' + v.value;
						}
						if ( v.value.substr(v.value.length - 1) != '"' ) {
							v.value = v.value + '"';
						}
					})
				}

				if ( record.type == "HTTP" ) {
					record_value = record.update.record_value.url.trim();
				}

				// Clean out empty values
				if ( record.update.record_type == "A" && record_value.length == 0 ) {
					return;
				}
				
				// Clean out empty values
				if ( record.update.record_type == "CNAME" && record.update.record_value[0].value == "" ) {
					return;
				}

				// Prepares new records
				if ( record.new ) {
					record.update.record_type = record.type
				}
				
				// Prepares new & modified records
				if ( record.edit || record.new ) {
					record.update.record_value = record_value
					record_updates.push( record.update );
				}

				// Prepares records to be removed
				if ( record.delete ) {
					record_updates.push({
						"record_id": record.id,
						"record_type": record.type,
						"record_name": record.name,
						"record_value": record_value,
						"record_ttl": record.ttl,
						"record_status": "remove-record"
					});
				}
			});
			
			if ( record_updates.length == 0 ) {
				this.snackbar.message = "No record changes found.";
				this.snackbar.show = true;
				this.dialog_domain.saving = false;
				return;
			}

			var data = {
				'action': 'captaincore_dns',
				'domain_key': domain_id,
				'record_updates': record_updates
			}

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_domain.results = response.data
					this.reflectDNS()
					
					// If no errors found then fetch new details
					// self.modifyDNS( self.dialog_domain.domain );
				})
				.catch( error => {
					this.dialog_domain.saving = false
					//self.dialog_domain.results = response.data;
				});
		},
		reflectDNS() {
			this.dialog_domain.results.forEach( result => {

				if ( result.record_status == "edit-record" && typeof result.errors == 'undefined' ) {
					record = this.dialog_domain.records.filter( r => r.id == result.record_id )[0];
					record.edit = false
					record.name = JSON.parse(JSON.stringify( record.update.record_name ))
					record.value = JSON.parse(JSON.stringify( record.update.record_value ))
					record.ttl = JSON.parse(JSON.stringify( record.update.record_ttl ))

					result.id = JSON.parse(JSON.stringify(result.data.id))
					result.name = JSON.parse(JSON.stringify(record.update.record_name))
					result.type = JSON.parse(JSON.stringify(record.update.record_type))
					if ( result.name == "" ) {
						result.success = `<code>${result.type.toUpperCase()}</code> record <code>@</code> updated successfully`
					} else {
						result.success = `<code>${result.type.toUpperCase()}</code> record <code>${result.name}</code> updated successfully`
					}
				}

				if ( result.record_status == "remove-record" && result.message == 'Record deleted' ) {
					record_to_remove = this.dialog_domain.records.filter( record => record.id == result.record_id );
					record_name = record_to_remove[0].name
					this.dialog_domain.records = this.dialog_domain.records.filter( record => record.id != result.record_id );
					if ( record_name == "" ) {
						result.success = `<code>${result.record_type.toUpperCase()}</code> record <code>@</code> deleted successfully`;
					} else {
						result.success = `<code>${result.record_type.toUpperCase()}</code> record <code>${record_name}</code> deleted successfully`;
					}
				}

				if ( result.record_status == "new-record" && typeof result.errors == 'undefined' && result.data.id != "" ) {
					if ( result.record_name == "" ) {
						result.success = `<code>${result.type.toUpperCase()}</code> record <code>@</code> added successfully`;
					} else {
						result.success = `<code>${result.type.toUpperCase()}</code> record <code>${result.record_name}</code> added successfully`;
					}

					// Remove existing new recording matching type, name, value and ttl.
					this.dialog_domain.records = this.dialog_domain.records.filter( r => {
						if ( r.update.record_status == "new-record" && r.update.record_name == result.record_name && r.update.record_type.toUpperCase() == result.type.toUpperCase() ) {
							return false
						}
						return true
					})

					if ( result.type == "a" || result.type == "aaaa" || result.type == "spf" ) {
						record_value = [];
						result.record_value.forEach( r => {
							record_value.push({ value: r.value, enabled: true });
						});
					} else {
						record_value = result.record_value
					}

					result.new = false
					result.edit = false
					result.delete = false
					result.value = JSON.parse(JSON.stringify(record_value))
					result.update = {
						"record_id": JSON.parse(JSON.stringify(result.data.id)),
						"record_type": JSON.parse(JSON.stringify(result.type)),
						"record_name": JSON.parse(JSON.stringify(result.record_name)),
						"record_value": JSON.parse(JSON.stringify(record_value)),
						"record_ttl": "3600",
						"record_status": "edit-record"
					}

					result.id = JSON.parse(JSON.stringify(result.data.id))
					result.name = JSON.parse(JSON.stringify(result.record_name))
					result.type = JSON.parse(JSON.stringify(result.type.toUpperCase()))
					result.ttl = "3600"

					// Add new record
					this.dialog_domain.records.push( result );

					// Sort new results
					this.dialog_domain.records.sort(function (record1, record2) {

						// Sort by types
						// If the first item has a higher number, move it down
						// If the first item has a lower number, move it up
						if (record1.type < record2.type) return -1;
						if (record1.type > record2.type) return 1;

						// If the votes number is the same between both items, sort alphabetically
						// If the first item comes first in the alphabet, move it up
						// Otherwise move it down
						if (record1.name > record2.name) return 1;
						if (record1.name < record2.name) return -1;

					});
				}

				this.dialog_domain.saving = false

			});
		},
		customerModifyPlan( subscription ) {
			this.dialog_customer_modify_plan.hosting_plans = JSON.parse(JSON.stringify( this.configurations.hosting_plans ))
			this.dialog_customer_modify_plan.subscription = JSON.parse(JSON.stringify( subscription ) )
			this.dialog_customer_modify_plan.selected_plan = subscription.plan.name
			this.dialog_customer_modify_plan.show = true
		},
		modifyPlan() {
			this.dialog_modify_plan.hosting_plans = JSON.parse(JSON.stringify( this.configurations.hosting_plans ))
			this.dialog_modify_plan.hosting_plans.push( {"name":"Custom","interval":"12","price":"","limits":{"visits":"","storage":"","sites":""}} )
			this.dialog_modify_plan.plan = JSON.parse(JSON.stringify( this.dialog_account.records.account.plan ))
			
            // Default billing mode if not set
            if ( typeof this.dialog_modify_plan.plan.billing_mode == "undefined" ) {
				this.dialog_modify_plan.plan.billing_mode = "standard"
			}

			// Adds commas
			if ( this.dialog_modify_plan.plan.limits.visits != null ) {
				this.dialog_modify_plan.plan.limits.visits = this.dialog_modify_plan.plan.limits.visits.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")
			}
			if ( this.dialog_modify_plan.plan.next_renewal == null ) {
				this.dialog_modify_plan.plan.next_renewal = ""
			}
			this.dialog_modify_plan.selected_plan = JSON.parse(JSON.stringify( this.dialog_account.records.account.plan.name ) )
			this.dialog_modify_plan.customer_name = this.dialog_site.site.account.name;
			this.dialog_modify_plan.show = true;
		},
		requestPlanChanges() {
			interval = "Month"
			this.hosting_intervals.forEach( i => {
				if ( i.value == this.dialog_customer_modify_plan.subscription.plan.interval ) {
					interval = i.text
				}
			})
			should_proceed = confirm( `Update account (${this.dialog_customer_modify_plan.subscription.name}) to ${this.dialog_customer_modify_plan.subscription.plan.name} and ${interval}?`);
			description = `Your account (${this.dialog_customer_modify_plan.subscription.name}) will be changed to ${this.dialog_customer_modify_plan.subscription.plan.name} and ${interval.toLowerCase()} here shortly.`
			if ( ! should_proceed ) {
				return;
			}
			var data = {
				'action': 'captaincore_account',
				'command': "requestPlanChanges",
				'value': this.dialog_customer_modify_plan.subscription,
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.snackbar.message = description
					this.snackbar.show = true

				})
				.catch( error => {
					this.snackbar.message = error;
					this.snackbar.show = true;
				});
			
		},
		cancelPlan() {
			should_proceed = confirm("Cancel plan '" + this.dialog_customer_modify_plan.subscription.name + "'? All sites will be removed.");
			if ( ! should_proceed ) {
				return;
			}

			description = "Requesting to cancel plan production site '" + this.dialog_customer_modify_plan.subscription.name + "'. Will send email notification once request completed.";
				
			var data = {
				'action': 'captaincore_account',
				'command': "cancelPlan",
				'value': this.dialog_customer_modify_plan.subscription,
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.snackbar.message = description
					this.snackbar.show = true

				})
				.catch( error => {
					this.snackbar.message = error;
					this.snackbar.show = true;
				});


		},
		updatePlan() {
			account_id = this.dialog_account.records.account.account_id
			plan = Object.assign( {}, this.dialog_modify_plan.plan )

			// Remove commas
			plan.limits.visits = plan.limits.visits.replace(/,/g, '')
			plan.addons = plan.addons.filter( addon => ! addon.required )
			this.dialog_account.records.account.plan.limits = plan.limits
			this.dialog_account.records.account.plan.name = plan.name
			this.dialog_account.records.account.plan.price = plan.price
			this.dialog_modify_plan.show = false;
			
			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': account_id,
				'command': "updatePlan",
				'value': { "plan": plan },
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.dialog_modify_plan = { show: false, site: {}, date_selector: false, hosting_plans: [], selected_plan: "", plan: { limits: {}, addons: [], next_renewal: "" }, customer_name: "", interval: "12" }
					this.showAccount( account_id )
			});

		},
		addPlanItem(type) {
			// type = 'addons', 'credits', or 'charges'
			this.dialog_modify_plan.plan[type].push({ "name": "", "quantity": "", "price": "" });
		},
		removePlanItem(type, index) {
			this.dialog_modify_plan.plan[type].splice(index, 1);
		},
		loadHostingPlan( selected_plan ) {
			if ( selected_plan ) {
				this.dialog_modify_plan.selected_plan = selected_plan
			}
			current_auto_pay = this.dialog_modify_plan.plan.auto_pay
			current_auto_switch = this.dialog_modify_plan.plan.auto_switch
			billing_user_id = this.dialog_modify_plan.plan.billing_user_id
			next_renewal = this.dialog_modify_plan.plan.next_renewal
			current_interval = JSON.parse(JSON.stringify( this.dialog_modify_plan.plan.interval ) )
			if ( typeof this.dialog_modify_plan.plan.addons != 'undefined' ) {
				current_addons = JSON.parse(JSON.stringify( this.dialog_modify_plan.plan.addons ) )
			}
			if ( typeof this.dialog_modify_plan.plan.charges != 'undefined' ) {
				current_charges = JSON.parse(JSON.stringify( this.dialog_modify_plan.plan.charges ) )
			}
			if ( typeof this.dialog_modify_plan.plan.credits != 'undefined' ) {
				current_credits = JSON.parse(JSON.stringify( this.dialog_modify_plan.plan.credits ) )
			}
			if ( !selected_plan ) {
				selected_plan = this.dialog_modify_plan.selected_plan
			}

			hosting_plan = this.dialog_modify_plan.hosting_plans.filter( plan => plan.name == selected_plan )[0]
			if ( typeof hosting_plan == "undefined" ) {
				return
			}
			if ( typeof hosting_plan.billing_mode == "undefined" ) {
				hosting_plan.billing_mode = "standard"
			}
			hosting_plan.addons = current_addons
			hosting_plan.charges = current_charges
			hosting_plan.credits = current_credits
			if ( current_auto_pay ) { 
				hosting_plan.auto_pay = JSON.parse(JSON.stringify( current_auto_pay ) )
			}
			if ( current_auto_switch ) {
				hosting_plan.auto_switch = JSON.parse(JSON.stringify( current_auto_switch ) )
			}
			if ( billing_user_id != "" ) {
				hosting_plan.billing_user_id = JSON.parse(JSON.stringify( billing_user_id ) )
			}
			if ( typeof next_renewal != "undefined" && next_renewal != "" ) {
				hosting_plan.next_renewal = JSON.parse(JSON.stringify( next_renewal ) )
			}
			this.dialog_modify_plan.plan = JSON.parse(JSON.stringify( hosting_plan ))
			if ( current_interval != hosting_plan.interval ) {
				this.dialog_modify_plan.plan.interval = current_interval
				this.dialog_modify_plan.plan.addons = current_addons
				this.calculateHostingPlan()
			}
		},
		calculateHostingPlan() {
			original_plan = this.dialog_modify_plan.hosting_plans.filter( p => p.name == this.dialog_modify_plan.selected_plan )[0]

			if ( ! original_plan.price || original_plan.price === "" ) {
				this.dialog_modify_plan.plan.price = "";
				return;
			}

			// Ensure we are working with numbers
			let original_price = parseFloat(original_plan.price);
			let original_interval = parseInt(original_plan.interval);
			let current_interval = parseInt(this.dialog_modify_plan.plan.interval);

			if ( current_interval == original_interval ) {
				this.dialog_modify_plan.plan.price = original_price;
			} else {
				// Calculate monthly unit price then multiply by new interval
				let unit_price = original_price / original_interval;
				this.dialog_modify_plan.plan.price = (unit_price * current_interval).toFixed(2);
			}
		},
		PushProductionToStaging( site_id ) {
			site = this.dialog_site.site
			environment = this.dialog_site.site.environments.filter( e => e.environment == "Production" )[0]
			site_name = environment.home_url
			site_name = site_name.replace( "https://www.", "" ).replace( "https://", "" ).replace( "http://www.", "" ).replace( "http://", "" )
			should_proceed = confirm( `Push '${site_name}' to staging environment?` )
			description = `Pushing '${site_name}' to staging environment.`

			if ( ! should_proceed ) {
				return;
			}

			if ( site.provider == "kinsta" ) {
				axios.post( '/wp-json/captaincore/v1/providers/kinsta/deploy-to-staging', {
					site_id: this.dialog_site.site.site_id
				}, {
					headers: { 'X-WP-Nonce':this.wp_nonce }
				})
				.then( response => {
					this.snackbar.message = `Deploying ${this.dialog_site.site.environments[0].home_url} to staging at Kinsta. Will notify once completed.`
					this.snackbar.show = true
					this.checkProviderActions()
				});
				return;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site.site_id,
				command: 'production-to-staging',
				value: this.current_user_email
			};

			self = this;

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );
		},
		PushStagingToProduction( site_id ) {

			site = this.dialog_site.site
			environment = this.dialog_site.site.environments.filter( e => e.environment == "Staging" )[0]
			site_name = environment.home_url
			site_name = site_name.replace( "https://", "" ).replace( "http://", "" )
			should_proceed = confirm( `Push '${site_name}' to production environment?` )
			description = `Pushing '${site_name}' to production environment`

			if ( ! should_proceed ) {
				return;
			}

			if ( site.provider == "kinsta" ) {
				axios.post( '/wp-json/captaincore/v1/providers/kinsta/deploy-to-production', {
						site_id: this.dialog_site.site.site_id
					}, {
						headers: { 'X-WP-Nonce':this.wp_nonce }
					})
					.then( response => {
						this.snackbar.message = `Deploying ${this.dialog_site.site.environments[1].home_url} to production at Kinsta. Will notify once completed.`
						this.snackbar.show = true
						this.checkProviderActions()
					});
				return;
			}

			var data = {
				action: 'captaincore_install',
				post_id: site.site_id,
				command: 'staging-to-production',
				value: this.current_user_email
			};

			self = this;

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
					self.snackbar.message = description;
					self.snackbar.show = true;
				})
				.catch( error => console.log( error ) );
		},
		viewApplyHttpsUrls( site_id ) {
			site = this.dialog_site.site
			this.dialog_apply_https_urls.show = true;
			this.dialog_apply_https_urls.site_id = site_id
			this.dialog_apply_https_urls.site_name = this.dialog_site.environment_selected.home_url;
		},
		viewApplyHttpsUrlsBulk() {
			this.dialog_apply_https_urls.show = true;
			this.dialog_apply_https_urls.site_id = this.sites_selected.map( s => s.site_id );
			this.dialog_apply_https_urls.site_name = this.sites_selected.length + " sites";
		},
		RollbackUpdate( hash, addon_type, addon_name, created_at, isActive ) {
			site = this.dialog_site.site
			date = this.pretty_timestamp_epoch( created_at );
			description = "Rollback "+ addon_type + " " + addon_name +" to version as of " + date + " on " + site.name;

			if ( isActive && typeof isActive.value == "boolean" ) {
				isActive.value = false
			}

			should_proceed = confirm( description + "?");

			if ( ! should_proceed ) {
				return;
			}

			site = this.dialog_site.site

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			this.dialog_site.environment_selected.update_logs.forEach( log => {
				log.view_quicksave = false
			})

			axios.post(
				`/wp-json/captaincore/v1/quicksaves/${hash}/rollback`, {
						site_id: site.site_id, 
						environment: this.dialog_site.environment_selected.environment, 
						version: 'this',
						type: addon_type,
						value: addon_name
					},
					{ headers: {'X-WP-Nonce':this.wp_nonce} }
				)
				.then(response => {
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					this.runCommand( response.data );
					this.snackbar.message = "Rollback in progress.";
					this.snackbar.show = true;
				})
		},
		RollbackQuicksave( hash, addon_type, addon_name, version, isActive ){
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected;
			quicksave = environment.quicksaves.filter( quicksave => quicksave.hash == hash )[0];
			date = this.pretty_timestamp_epoch(quicksave.created_at);
			previous_date = this.pretty_timestamp_epoch(quicksave.previous_created_at);
			if ( version == "this" ) {
				description = "Rollback "+ addon_type + " " + addon_name +" to version as of " + date + " on " + site.name;
			}
			if ( version == "previous" ) {
				description = "Rollback "+ addon_type + " " + addon_name +" to version as of " + previous_date + " on " + site.name;
			}

			if ( isActive && typeof isActive.value == "boolean" ) {
				isActive.value = false
			}

			should_proceed = confirm( description + "?");

			if ( ! should_proceed ) {
				return;
			}

			site = this.dialog_site.site

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post(
				`/wp-json/captaincore/v1/quicksaves/${hash}/rollback`, {
						site_id: site.site_id, 
						environment: this.dialog_site.environment_selected.environment, 
						version: version,
						type: addon_type,
						value: addon_name
					},
					{ headers: {'X-WP-Nonce':this.wp_nonce} }
				)
				.then(response => {
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					this.runCommand( response.data );
					this.snackbar.message = "Rollback in progress.";
					this.snackbar.show = true;
				})
		},
		QuicksaveFileRestore() {
			date = this.pretty_timestamp_epoch(this.dialog_file_diff.quicksave.created_at);
			should_proceed = confirm("Rollback file " + this.dialog_file_diff.file_name  + " as of " + date);

			if ( ! should_proceed ) {
				return;
			}
			hash = this.dialog_file_diff.quicksave.hash
			if ( typeof this.dialog_file_diff.quicksave.hash == "undefined" ) {
				hash = this.dialog_file_diff.quicksave.hash_after
			}

			site = this.dialog_site.site

			description = "Rollback file " + this.dialog_file_diff.file_name  + " as of " + date

			// Start job
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			var data = {
				'action': 'captaincore_install',
				'post_id': site.site_id,
				'environment': this.dialog_site.environment_selected.environment,
				'hash': hash,
				'command': 'quicksave_file_restore',
				'value'	: this.dialog_file_diff.file_name,
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data
					this.runCommand( response.data )
					this.dialog_file_diff.show = false
				})
				.catch( error => console.log( error ) );

		},
		QuicksaveFileDiff( hash, file_name ) {
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected
			file_name = file_name.split("	")[1]
			this.dialog_file_diff.response = ""
			this.dialog_file_diff.file_name = file_name
			this.dialog_file_diff.loading = true
			this.dialog_file_diff.quicksave = environment.quicksaves.filter(quicksave => quicksave.hash == hash)[0]
			this.dialog_file_diff.show = true

			axios.get(
				`/wp-json/captaincore/v1/quicksaves/${hash}/filediff`, {
					headers: {'X-WP-Nonce':this.wp_nonce},
					params: { site_id: site.site_id, environment: environment.environment.toLowerCase(), file: file_name }
				})
				.then(response => {
					let html = []
					JSON.parse ( JSON.stringify (  response.data ) ).split('\n').forEach(line => {
						applied_css="";
						if ( line[0] == "-" ) {
							applied_css=" class='change-removed'";
						}
						if ( line[0] == "+" ) {
							applied_css=" class='change-added'";
						}
						html.push("<div"+applied_css+">" + line + "</div>");
					});
					this.dialog_file_diff.response = html.join('\n')
					this.dialog_file_diff.loading = false
				})
		},
		QuicksaveFileDiffUpdate( hash, file_name ) {
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected
			file_name = file_name.split("	")[1]
			this.dialog_file_diff.response = ""
			this.dialog_file_diff.file_name = file_name
			this.dialog_file_diff.loading = true
			this.dialog_file_diff.quicksave = environment.update_logs.filter(quicksave => quicksave.hash_after == hash)[0]
			this.dialog_file_diff.show = true

			axios.get(
				`/wp-json/captaincore/v1/quicksaves/${hash}/filediff`, {
					headers: {'X-WP-Nonce':this.wp_nonce},
					params: { site_id: site.site_id, environment: environment.environment.toLowerCase(), file: file_name }
				})
				.then(response => {
					let html = []
					JSON.parse ( JSON.stringify (  response.data ) ).split('\n').forEach(line => {
						applied_css="";
						if ( line[0] == "-" ) {
							applied_css=" class='change-removed'";
						}
						if ( line[0] == "+" ) {
							applied_css=" class='change-added'";
						}
						html.push("<div"+applied_css+">" + line + "</div>");
					});
					this.dialog_file_diff.response = html.join('\n')
					this.dialog_file_diff.loading = false
				})
		},
		QuicksaveCheck( site_id ) {
			site = this.dialog_site.site
			should_proceed = confirm("Check for new files on " + site.name + "?")

			if ( ! should_proceed ) {
				return;
			}

			// Start job
			site_name = site.name;
			description = "Checking for file changes on " + site_name;
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': 'quick_backup',
				'environment': this.dialog_site.environment_selected.environment,
			};

			self = this;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					
					// Updates job id with reponsed background job id
					self.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					self.runCommand( response.data );
					self.snackbar.message = "Quicksave in process.";
					self.snackbar.show = true;
					
				})
				.catch( error => console.log( error ) );

		},
		QuicksavesRollback( site_id, quicksave, version ) {
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected
			created_at = quicksave.created_at
			if ( version == 'previous' ) {
				created_at = quicksave.previous_created_at
			}
			date = this.pretty_timestamp_epoch(created_at)
			should_proceed = confirm("Will rollback all themes/plugins on " + environment.home_url + " to " + date + ". Proceed?")

			if ( ! should_proceed ) {
				return;
			}

			// Start job
			description = "Quicksave rollback all themes/plugins on " + site.name + " to " + date + ".";
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post(
				`/wp-json/captaincore/v1/quicksaves/${quicksave.hash}/rollback`, {
						site_id: site.site_id, 
						environment: environment.environment, 
						version: version,
						type: "all"
					},
					{ headers: {'X-WP-Nonce':this.wp_nonce} }
				)
				.then(response => {
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					quicksave.loading = false
					this.runCommand( response.data );
					this.snackbar.message = "Rollback in progress.";
					this.snackbar.show = true;
				})
		},
		rollbackUpdates( site_id, quicksave, previous ) {
			hash = quicksave.hash_after
			created_at = quicksave.created_at
			if ( previous ) {
				hash = quicksave.hash_before
				created_at = quicksave.started_at
			}
			date = this.pretty_timestamp_epoch(created_at)
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected
			should_proceed = confirm("Will rollback all themes/plugins on " + environment.home_url + " to " + date + ". Proceed?")

			if ( ! should_proceed ) {
				return;
			}

			// Start job
			description = "Quicksave rollback all themes/plugins on " + site.name + " to " + date + ".";
			job_id = Math.round((new Date()).getTime());
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			axios.post(
				`/wp-json/captaincore/v1/quicksaves/${hash}/rollback`, {
						site_id: site.site_id, 
						environment: environment.environment, 
						version: 'this',
						type: "all"
					},
					{ headers: {'X-WP-Nonce':this.wp_nonce} }
				)
				.then(response => {
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					quicksave.view_quicksave = false;
					this.runCommand( response.data );
					this.snackbar.message = "Rollback in progress.";
					this.snackbar.show = true;
				})

		},
		viewQuicksavesChanges( site_id, quicksave ) {
			site = this.dialog_site.site
			quicksave.view_changes = true;

			axios.get(
				`/wp-json/captaincore/v1/quicksaves/${quicksave.hash}/changed`, {
					headers: {'X-WP-Nonce':this.wp_nonce},
					params: { site_id: site_id, environment: this.dialog_site.environment_selected.environment.toLowerCase() }
				})
				.then(response => { 
					quicksave.view_files = response.data.trim().split("\n");
					quicksave.filtered_files = response.data.trim().split("\n");
					quicksave.loading = false;
				});
		},
		viewQuicksavesChangesItem( item, match ) {
			item.response = []
			hash = item.hash
			site = this.dialog_site.site
			if ( typeof hash == 'undefined' ) {
				hash = item.hash_after
			}
			axios.get(
				`/wp-json/captaincore/v1/quicksaves/${hash}/changed`, {
					headers: {'X-WP-Nonce':this.wp_nonce},
					params: { site_id: site.site_id, environment: this.dialog_site.environment_selected.environment.toLowerCase(), match: match }
				})
				.then(response => { 
					item.response = response.data.trim().split("\n")
				});
		},
		handleExpansionUpdate(newExpandedArray) {
			if (newExpandedArray.length > this.expanded.length) {
				const newlyExpandedItemKey = newExpandedArray[newExpandedArray.length - 1];
				this.expanded = [newlyExpandedItemKey];
			} else {
				this.expanded = newExpandedArray;
			}	
		},
		viewQuicksaves() {
			axios.get(
				'/wp-json/captaincore/v1/quicksaves', {
					headers: {'X-WP-Nonce':this.wp_nonce},
					params: { site_id: this.dialog_site.site.site_id, environment: this.dialog_site.environment_selected.environment.toLowerCase() }
				})
				.then(response => { 
					this.dialog_site.environment_selected.quicksaves = response.data
				});
		},
		viewUpdateLogs() {
			axios.get(
				'/wp-json/captaincore/v1/update-logs', {
					headers: {'X-WP-Nonce':this.wp_nonce},
					params: { site_id: this.dialog_site.site.site_id, environment: this.dialog_site.environment_selected.environment.toLowerCase() }
				})
				.then(response => { 
					this.dialog_site.environment_selected.update_logs = response.data
				});
		},
		saveBackupConfigurations() {
			site_id = this.dialog_site.site.site_id
			axios.post( `/wp-json/captaincore/v1/sites/${site_id}/backup`, {
				settings: this.dialog_site.site.backup_settings
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.snackbar.message = `Backup settings for ${this.dialog_site.site.name} has been updated.`
				this.snackbar.show = true
				this.dialog_backup_configurations = { show: false, settings: { mode: "", interval: "", active: true } }
			});
		},
		downloadBackup(backup_id, backup_tree) {
			const site_id = this.dialog_site.site.site_id;
			const selectedFilePaths = [];
			const selectedDirectoryPaths = [];

			if (!backup_tree || backup_tree.length === 0) {
				this.snackbar.message = "No items selected for download.";
				this.snackbar.show = true;
				return;
			}

			// backup_tree is an array of paths (strings).
			const allSelectedPaths = new Set(backup_tree);

			for (const itemPath of backup_tree) {
				const parentPath = this.getParentPath(itemPath);

				// Only process this item if its parent is NOT also selected,
				// or if it has no parent (is a root item).
				if (parentPath === null || !allSelectedPaths.has(parentPath)) {
					// Find the node in the full tree to get its type
					const node = this.findNodeByPath(this.backup_set_files, itemPath);
					if (node) { // Check if node was found
						if (node.type === "file") {
							selectedFilePaths.push(itemPath);
						} else if (node.type === "dir") {
							selectedDirectoryPaths.push(itemPath);
						}
					}
				}
			}

			if (selectedFilePaths.length === 0 && selectedDirectoryPaths.length === 0) {
				// This can happen if only child items of an already selected parent were in backup_tree
				// but got filtered out. Or if backup_tree was empty to begin with.
				this.snackbar.message = "No top-level items to download based on selection.";
				this.snackbar.show = true;
				return;
			}
			
			const totalTopLevelItems = selectedFilePaths.length + selectedDirectoryPaths.length;
			const description = `Generating downloadable zip for ${totalTopLevelItems} top-level item(s). Will send an email when ready.`;
			const job_id = Math.round((new Date()).getTime());
			
			this.jobs.push({"job_id": job_id,"description": description, "status": "done", stream: [], "command": "downloadBackup"})

			const data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'command': "backup_download",
				'value': {
					files: JSON.stringify(selectedFilePaths),
					directories: JSON.stringify(selectedDirectoryPaths),
					backup_id: backup_id,
				},
				'environment': this.dialog_site.environment_selected.environment,
			};

			axios.post(ajaxurl, Qs.stringify(data))
				.then(response => {
					this.snackbar.message = description; // Or a more specific success message
					this.snackbar.show = true;
					// Reset all file selections for the current item
					const currentBackupItem = this.dialog_site.environment_selected.backups.find(b => b.id === backup_id);
					if (currentBackupItem) {
						currentBackupItem.tree = [];
						if (this.calculateTreeStorage) { // If you have this method
							this.calculateTreeStorage(currentBackupItem);
						}
					}
				})
				.catch(error => {
					console.error("Error requesting backup download:", error);
					this.snackbar.message = "Failed to start download process.";
					this.snackbar.show = true;
					const jobIndex = this.jobs.findIndex(job => job.job_id === job_id);
					if (jobIndex !== -1) {
						this.jobs[jobIndex].status = "error";
					}
				});
		},
		searchQuicksave() {
			this.quicksave_search_results.loading = true
			site_id = this.dialog_site.site.site_id
			environment = this.dialog_site.environment_selected.environment.toLowerCase()
			search = `${this.quicksave_search_type}:${this.quicksave_search_field}:${this.quicksave_search}`
			axios.get(
				`/wp-json/captaincore/v1/quicksaves/search`, {
					headers: {'X-WP-Nonce':this.wp_nonce},
					params: { site_id: site_id, environment: environment, search: search }
				})
				.then(response => {
					this.quicksave_search_results.loading = false
					this.quicksave_search_results.search = this.quicksave_search
					this.quicksave_search_results.search_type = this.quicksave_search_type
					this.quicksave_search_results.search_field = this.quicksave_search_field
					this.quicksave_search_results.items = response.data
				});
		},
		getQuicksave( hash, site_id ) {
			environment = this.dialog_site.environment_selected.environment.toLowerCase()
			axios.get(
				`/wp-json/captaincore/v1/quicksaves/${hash}`, {
					headers: {'X-WP-Nonce':this.wp_nonce},
					params: { site_id: site_id, environment: environment }
				})
				.then(response => {
					quicksave_selected = this.dialog_site.environment_selected.quicksaves.filter( q => q.hash == hash )
					if ( quicksave_selected.length != 1 ) {
						return
					}
					quicksave_selected[0].previous_created_at = response.data.previous_created_at
					quicksave_selected[0].plugins = response.data.plugins
					quicksave_selected[0].plugins_deleted = response.data.plugins_deleted
					quicksave_selected[0].themes = response.data.themes
					quicksave_selected[0].themes_deleted = response.data.themes_deleted
					quicksave_selected[0].core = response.data.core
					quicksave_selected[0].status = response.data.status
					quicksave_selected[0].loading = false
				});
		},
		getUpdateLogQuicksave( hash_before, hash_after, site_id ) {
			environment = this.dialog_site.environment_selected.environment.toLowerCase()
			axios.get(
				`/wp-json/captaincore/v1/update-logs/${hash_before}_${hash_after}`, {
					headers: {'X-WP-Nonce':this.wp_nonce},
					params: { site_id: site_id, environment: environment }
				})
				.then(response => {
					quicksave_selected = this.dialog_site.environment_selected.update_logs.filter( q => q.hash_before == hash_before && q.hash_after == hash_after )
					if ( quicksave_selected.length != 1 ) {
						return
					}
					quicksave_selected[0].previous_created_at = response.data.previous_created_at
					quicksave_selected[0].plugins = response.data.plugins
					quicksave_selected[0].plugins_deleted = response.data.plugins_deleted
					quicksave_selected[0].themes = response.data.themes
					quicksave_selected[0].themes_deleted = response.data.themes_deleted
					quicksave_selected[0].core = response.data.core
					quicksave_selected[0].status = response.data.status
					quicksave_selected[0].loading = false
				});
		},
		mapNodeForTreeview(node) {
			const mappedNode = {
				id: node.id,
				name: node.name,
				size: node.size,
				count: node.count,
				type: node.type,
				path: node.path
			};
			if (node.children && node.children.length > 0) {
				mappedNode.children = []; // Mark that children exist but are not loaded
			} else if (node.children && node.children.length === 0) {
				mappedNode.children = []; // Explicitly an empty folder, already "loaded"
			}
			// If no 'children' property, it's a file, so no 'childNodes'
			return mappedNode;
		},
		getBackup(backup_id, site_id) {
			const environment = this.dialog_site.environment_selected.environment.toLowerCase();
			const backup_item = this.dialog_site.environment_selected.backups.find(b => b.id === backup_id);

			if (!backup_item) {
				console.error("Target backup item not found in local list:", backup_id);
				return;
			}

			axios.get(
				`/wp-json/captaincore/v1/sites/${site_id}/${environment}/backups/${backup_id}`, {
					headers: { 'X-WP-Nonce': this.wp_nonce }
				})
				.then(response1 => {
					if (response1.data && typeof response1.data === 'string' && response1.data.includes("https://")) {
						const dataUrl = response1.data;
						axios.get(dataUrl)
							.then(response2 => {
								files = response2.data.files || [];
								backup_item.omitted = response2.data.omitted || false;

								if (files && files.length > 0) {
									this.sortTree(files);
								}
								
								// Initialize/reset properties for the expanded view and v-treeview
								backup_item.tree = [];
								backup_item.active = [];
								backup_item.active_node = null;
								backup_item.preview = ''; // Reset preview content
								this.backup_set_files = files;
								backup_item.files = files.map(this.mapNodeForTreeview);
								backup_item.calculated_total = 0
								backup_item.loading = false;
							})
							.catch(error2 => {
								console.error(`Error fetching backup data from URL (${dataUrl}):`, error2);
								backup_item.calculated_total = 0;
								backup_item.loading = false;
								backup_item.files = []; // Set a default state on error
								backup_item.omitted = true; // Indicate an issue
								backup_item.tree = [];
								backup_item.active = [];
								backup_item.preview = 'Error loading files.'; // Provide feedback
							});
					} else {
						console.warn("Initial backup API response was not a valid URL or did not meet criteria. Data:", response1.data);
						backup_item.calculated_total = 0;
						backup_item.loading = false;
						backup_item.files = [];
						backup_item.omitted = true;
						backup_item.tree = [];
						backup_item.active = [];
						backup_item.preview = 'Could not retrieve file list location.';
					}
				})
				.catch(error1 => {
					console.error("Error fetching initial backup information:", error1);
					if (backup_item) { // Ensure backup_item exists before modifying
						backup_item.calculated_total = 0;
						backup_item.loading = false;
						backup_item.files = [];
						backup_item.omitted = true;
						backup_item.tree = [];
						backup_item.active = [];
						backup_item.preview = 'Error retrieving backup details.';
					}
				});
		},
		previewFile(item) {
			item.preview = "";
			item.active_node = null;
			item.isPreviewImage = false; 

			if (item.active && item.active.length === 1) {
				const filePath = item.active[0];
				const node = this.findNodeByPath(this.backup_set_files, filePath);

				if (node) {
					item.active_node = node; // Store the node regardless of type

					// Scroll to the title after the DOM has updated
					this.$nextTick(() => {
						if (this.$refs.filePreviewTitle) {
							this.goTo(this.$refs.filePreviewTitle, { duration: 300, offset: -120 });
						}
					});

					if (node.type === 'dir') {
						// It's a directory, calculate stats and attach them
						const stats = this.calculateFolderStats(node);
						item.active_node.stats = stats;
					} else if (node.type === 'file') {
						const site_id = this.dialog_site.site.site_id;
						const environment = this.dialog_site.environment_selected.environment.toLowerCase();
						const extension = (node.ext || '').split('.').pop().toLowerCase();
						const imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp']; // Remove 'svg' from here

						// Handle SVG as text (XML) for inline rendering
						if (extension === 'svg') {
							item.isPreviewImage = false; // Not treated as img
							axios.get(`/wp-json/captaincore/v1/sites/${site_id}/${environment}/backups/${item.id}?file=${filePath}`, {
								headers: { 'X-WP-Nonce': this.wp_nonce }
							})
							.then(response => {
								item.preview = response.data; // Raw SVG XML string for v-html
							})
							.catch(error => {
								console.error("Error fetching SVG preview:", error);
								item.preview = "Error loading SVG.";
							});
							return;
						}

						// Handle raster images as before (blob -> data URL)
						if (imageExtensions.includes(extension)) {
							item.isPreviewImage = true;
							axios.get(`/wp-json/captaincore/v1/sites/${site_id}/${environment}/backups/${item.id}?file=${filePath}`, {
								headers: { 'X-WP-Nonce': this.wp_nonce },
								responseType: 'blob'
							})
							.then(response => {
								const reader = new FileReader();
								reader.readAsDataURL(response.data);
								reader.onloadend = () => {
									item.preview = reader.result;
								};
							})
							.catch(error => {
								console.error("Error fetching image preview:", error);
								item.preview = "Error loading image.";
							});
							return;
						}

						// Handle other text/code files as before
						if (node.size > 500000) {
							item.preview = "too-large";
							return;
						}

						item.isPreviewImage = false;

						axios.get(
							`/wp-json/captaincore/v1/sites/${site_id}/${environment}/backups/${item.id}?file=${filePath}`, {
								headers: { 'X-WP-Nonce': this.wp_nonce }
							})
							.then(response => {
								const content = response.data;
								const langExtension = node.ext || 'markup';
								const language = Prism.languages[langExtension] ? langExtension : 'markup';

								if (Prism.languages[language]) {
									item.preview = Prism.highlight(content, Prism.languages[language], language);
								} else {
									// Fallback for unsupported languages: escape HTML to prevent rendering issues
									const esc = document.createElement('textarea');
									esc.textContent = content;
									item.preview = esc.innerHTML;
								}
							})
							.catch(error => {
								console.error("Error fetching file preview:", error);
								item.preview = "Error loading preview.";
							});
					}
				} else {
					item.preview = "Could not find file details.";
				}
			}
		},
		calculateTreeStorage(currentItem, selectedPaths) {
			if (!currentItem || !selectedPaths || selectedPaths.length === 0) {
				if (currentItem) currentItem.calculated_total = 0;
				return;
			}

			let newTotalSize = 0;
			const selectedPathsSet = new Set(selectedPaths);

			for (const nodePath of selectedPaths) {
				const parentPath = this.getParentPath(nodePath);

				// Only add the size if the parent folder is not also selected, to avoid double counting.
				if (parentPath === null || !selectedPathsSet.has(parentPath)) {
					const rawNode = this.findNodeByPath(this.backup_set_files, nodePath);
					if (rawNode) {
						// Recursively get the size of the entire node (folder or file)
						newTotalSize += this.getActualNodeSize(rawNode);
					} else {
						console.warn(`Raw node not found for path: ${nodePath}.`);
					}
				}
			}
			currentItem.calculated_total = newTotalSize;
		},
		calculateFolderStats(folderNode) {
			let totalSize = 0;
			let fileCount = 0;

			if (!folderNode || !Array.isArray(folderNode.children)) {
				return { totalSize, fileCount };
			}

			for (const child of folderNode.children) {
				if (child.type === 'file') {
					totalSize += child.size || 0;
					fileCount++;
				} else if (child.type === 'dir') {
					const stats = this.calculateFolderStats(child);
					totalSize += stats.totalSize;
					fileCount += stats.fileCount;
				}
			}

			return { totalSize, fileCount };
		},
		viewBackups() {
			site_id = this.dialog_site.site.site_id
			environment = this.dialog_site.environment_selected.environment.toLowerCase()
			axios.get(
				`/wp-json/captaincore/v1/site/${site_id}/${environment}/backups`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => {
					this.dialog_site.environment_selected.backups = response.data
				});
		},
		handleRowClick( item ) {
			const currentExpanded = this.dialog_site.environment_selected.expanded_backups;
			const itemId = item.id; // Corresponds to `item-value="id"` on v-data-table
			if (currentExpanded.includes(itemId)) {
				this.dialog_site.environment_selected.expanded_backups = [];
			} else {
				this.dialog_site.environment_selected.expanded_backups = [itemId];
			}
		},
		findNodeByPath(nodes, path) {
			for (const node of nodes) {
				if (node.path === path) {
					return node;
				}
				if (node.type === 'dir' && Array.isArray(node.children)) {
					const found = this.findNodeByPath(node.children, path);
					if (found) return found;
				}
			}
			return null;
		},
		getAllDescendantPaths(node) {
			let paths = [];
			if (node && node.children && Array.isArray(node.children)) {
				for (const child of node.children) {
					paths.push(child.path);
					paths = paths.concat(this.getAllDescendantPaths(child));
				}
			}
			return paths;
		},
		handleTreeSelection(backupItem, newSelectedPaths) {
			// `lastCalculatedTree` holds the state from our function's last run. This is our reliable "old" state.
			const fullTreeData = this.backup_set_files;
			// Note: `backupItem.tree` is already updated by v-model, so we use our own state tracking property.
			const oldSelection = new Set(backupItem.lastCalculatedTree || []);
			const newSelectionFromComponent = new Set(newSelectedPaths);

			// Determine the single path that was toggled by the user by comparing the component's new state
			// with our last known state.
			const addedPath = newSelectedPaths.find(path => !oldSelection.has(path));
			const removedPath = (backupItem.lastCalculatedTree || []).find(path => !newSelectionFromComponent.has(path));

			// Start with the reliable "old" selection and apply the detected change.
			const finalSelection = new Set(backupItem.lastCalculatedTree || []);

			if (addedPath) {
				// A path was added. Add it and all its descendants to our selection set.
				finalSelection.add(addedPath);
				const node = this.findNodeByPath(fullTreeData, addedPath);
				if (node && node.type === 'dir') {
					const descendants = this.getAllDescendantPaths(node);
					descendants.forEach(descendantPath => finalSelection.add(descendantPath));
				}
			} else if (removedPath) {
				// A path was removed. Remove it, its descendants, and any parent paths from our selection set.
				finalSelection.delete(removedPath);
				const node = this.findNodeByPath(fullTreeData, removedPath);
				if (node && node.type === 'dir') {
					const descendants = this.getAllDescendantPaths(node);
					descendants.forEach(descendantPath => finalSelection.delete(descendantPath));
				}
				
				// Recursively uncheck parents
				let parentPath = this.getParentPath(removedPath);
				while (parentPath) {
					finalSelection.delete(parentPath);
					parentPath = this.getParentPath(parentPath);
				}
			}

			const finalTreeArray = Array.from(finalSelection);
			
			// === CRITICAL STEP ===
			// 1. Update the v-model with our calculated, correct selection state.
			backupItem.tree = finalTreeArray;
			// 2. Store this state for the next time the function is called.
			backupItem.lastCalculatedTree = finalTreeArray;

			// Recalculate storage in the next DOM update cycle.
			this.$nextTick(() => {
				this.calculateTreeStorage(backupItem, backupItem.tree);
			});
		},
		handleLoadChildren(item) { // 'item' is the treeview node being expanded
			const originalRawNode = this.findNodeByPath(this.backup_set_files, item.path);
			if (originalRawNode && originalRawNode.children && Array.isArray(originalRawNode.children)) {
				item.children = originalRawNode.children.map(childNode => this.mapNodeForTreeview(childNode));
			} else {
				item.children = [];
			}
		},
		getParentPath(path) {
			if (!path || path === "/") {
				return null; // Root or invalid path has no parent to check against
			}
			const lastSlashIndex = path.lastIndexOf('/');
			if (lastSlashIndex === -1) {
				// This case should ideally not happen if all paths are absolute
				return null;
			}
			if (lastSlashIndex === 0) {
				// Parent of "/foo" is "/"
				return "/";
			}
			// Parent of "/foo/bar" is "/foo"
			return path.substring(0, lastSlashIndex);
		},
		getActualNodeSize(rawNode) {
			if (!rawNode) {
				return 0;
			}
			if (rawNode.type === 'file') {
				return rawNode.size || 0;
			}
			if (rawNode.type === 'dir') {
				let totalSize = 0; // Folders themselves often have size 0 in listings
				if (Array.isArray(rawNode.children)) {
					for (const childRawNode of rawNode.children) {
						totalSize += this.getActualNodeSize(childRawNode); // Recursive call
					}
				}
				return totalSize;
			}
			return 0;
		},
		viewSnapshots() {
			site = this.dialog_site.site
			axios.get(
				'/wp-json/captaincore/v1/site/'+ site.site_id +'/snapshots', {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then(response => { 
					// Dynamically map snapshots to their specific environment object
					site.environments.forEach( env => {
						if ( response.data[ env.environment ] ) {
							env.snapshots = response.data[ env.environment ]
						} else {
							env.snapshots = []
						}
					})
				});
		},
		activateTheme( theme_name, site_id ) {
			const site = this.dialog_site.site;
			const env = this.dialog_site.environment_selected;

			// Enable loading progress
			site.loading_themes = true;
			
			// Optimistic UI update
			env.themes.filter(theme => theme.name != theme_name).forEach( theme => theme.status = "inactive" );

			// Start job
			const site_name = site.name;
			const description = "Activating theme '" + theme_name + "' on " + site_name;
			const jobId = Math.round((new Date()).getTime());
			
			const onFinish = () => {
				site.loading_themes = false;
			};

			this.jobs.push({
				"job_id": jobId,
				"description": description, 
				"status": "queued", 
				"stream": [],
				"on_finish": onFinish
			});

			// WP ClI command to send
			const wpcli = `wp theme activate ${theme_name} --skip-themes --skip-plugins`;

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: [env.environment_id],
				code: wpcli
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand( response.data );
				}
			})
			.catch(error => {
				console.log(error.response);
				site.loading_themes = false;
			});
		},
		deleteTheme (theme_name, site_id) {
			let should_proceed = confirm("Are you sure you want to delete theme " + theme_name + "?");

			if ( ! should_proceed ) {
				return;
			}

			const site = this.dialog_site.site;
			const env = this.dialog_site.environment_selected;

			// Enable loading progress
			site.loading_themes = true;
			const description = "Deleting theme '" +theme_name + "' from " + site.name;
			const jobId = Math.round((new Date()).getTime());
			
			const onFinish = () => {
				site.loading_themes = false;
			};

			this.jobs.push({
				"job_id": jobId,
				"description": description, 
				"status": "queued", 
				"stream": [],
				"on_finish": onFinish
			});

			// WP ClI command to send
			const wpcli = `wp theme delete ${theme_name} --skip-themes --skip-plugins`;

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: [env.environment_id],
				code: wpcli
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				// Optimistic update
				const updated_themes = env.themes.filter(theme => theme.name != theme_name);
				env.themes = updated_themes;

				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand( response.data );
				}
			})
			.catch(error => {
				console.log(error.response);
				site.loading_themes = false;
			});
		},
		addPlugin ( site_id ){
			site = this.dialog_site.site
			this.new_plugin.show = true
			this.new_plugin.sites = [ site ]
			this.new_plugin.site_name = this.dialog_site.environment_selected.home_url
			this.new_plugin.current_plugins = this.dialog_site.environment_selected.plugins.map( p => p.name )
			this.new_plugin.environment_selected = this.dialog_site.environment_selected.environment
			this.fetchPlugins()
			this.fetchEnvatoPlugins()
		},
		addPluginBulk() {
			this.new_plugin.show = true
			this.new_plugin.sites = this.sites_selected
			this.new_plugin.site_name = this.new_plugin.sites.length + " sites"
			this.new_plugin.current_plugins = []
			
			// Default to Production if multiple, or specific if mapped from terminal
			this.new_plugin.environment_selected = "Production" 
			
			this.fetchPlugins()
			this.fetchEnvatoPlugins()
		},
		installEnvatoPlugin ( plugin ) {
			const site_count_label = this.new_plugin.sites.length === 1 ? this.new_plugin.sites[0].name : `${this.new_plugin.sites.length} sites`;
			let should_proceed = confirm("Proceed with installing plugin " + plugin.name + " on " + site_count_label + "?");
			if ( ! should_proceed ) {
				return;
			}
			
			this.new_plugin.show = false;
			this.snackbar.message = `Downloading ${plugin.name} from Envato...`;
			this.snackbar.show = true;

			axios.get(
				`/wp-json/captaincore/v1/providers/envato/plugin/${plugin.id}/download`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
			.then( response => {
				const downloadUrl = response.data;
				const installJobId = Math.round((new Date()).getTime());

				let envName = this.new_plugin.environment_selected || "Production";
				if ( this.new_plugin.sites.length == 1 && this.new_plugin.sites[0].environment_selected ) {
					envName = this.new_plugin.sites[0].environment_selected.environment || envName;
				}
				const envIds = this.getEnvironmentIdsFromSelection(this.new_plugin.sites, envName);

				if (envIds.length === 0) return;

				const onInstallFinish = () => {
					const syncDesc = `Syncing data for ${site_count_label}`;
					const syncJobId = installJobId + 1;
					
					this.jobs.push({
						"job_id": syncJobId,
						"description": syncDesc, 
						"status": "queued", 
						"stream": [],
						"command": "syncSite",
						"site_id": this.new_plugin.sites.map(s => s.site_id)
					});

					axios.post('/wp-json/captaincore/v1/sites/bulk-tools', {
						tool: 'sync-data',
						environments: envIds
					}, { headers: { 'X-WP-Nonce': this.wp_nonce } })
					.then(res => {
						const job = this.jobs.find(j => j.job_id == syncJobId);
						if (job) {
							job.job_id = res.data;
							this.runCommand(res.data);
						}
					});
				};

				this.jobs.push({
					"job_id": installJobId,
					"description": `Installing plugin '${plugin.name}' to ${site_count_label}`, 
					"status": "queued", 
					"stream": [],
					"on_finish": onInstallFinish
				});

				const wpcli = `wp plugin install --force --skip-plugins --skip-themes '${downloadUrl}'`;

				axios.post(`/wp-json/captaincore/v1/run/code`, {
					environments: envIds,
					code: wpcli
				}, { headers: { 'X-WP-Nonce': this.wp_nonce } })
				.then(res => {
					const job = this.jobs.find(j => j.job_id == installJobId);
					if (job) {
						job.job_id = res.data;
						this.runCommand(res.data);
					}
				});

				// Clear dialog state
				this.new_plugin.api.items = [];
				this.new_plugin.api.info = {};
				this.new_plugin.envato = { items: [], search: "" };
				this.new_plugin.loading = false;
			})
			.catch(error => {
				console.log(error.response);
				this.snackbar.message = "Failed to get download link.";
				this.snackbar.show = true;
				this.new_plugin.show = true;
			});
		},
		installEnvatoTheme ( theme ) {
			const site_count_label = this.new_theme.sites.length === 1 ? this.new_theme.sites[0].name : `${this.new_theme.sites.length} sites`;
			let should_proceed = confirm("Proceed with installing theme " + theme.name + " on " + site_count_label + "?");
			if ( ! should_proceed ) {
				return;
			}
			
			this.new_theme.show = false;
			this.snackbar.message = `Downloading ${theme.name} from Envato...`;
			this.snackbar.show = true;

			axios.get(
				`/wp-json/captaincore/v1/providers/envato/theme/${theme.id}/download`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
			.then( response => {
				const downloadUrl = response.data;
				const installJobId = Math.round((new Date()).getTime());

				let envName = this.new_theme.environment_selected || "Production";
				if ( this.new_theme.sites.length == 1 && this.new_theme.sites[0].environment_selected ) {
					envName = this.new_theme.sites[0].environment_selected.environment || envName;
				}
				const envIds = this.getEnvironmentIdsFromSelection(this.new_theme.sites, envName);

				if (envIds.length === 0) return;

				const onInstallFinish = () => {
					const syncDesc = `Syncing data for ${site_count_label}`;
					const syncJobId = installJobId + 1;
					
					this.jobs.push({
						"job_id": syncJobId,
						"description": syncDesc, 
						"status": "queued", 
						"stream": [],
						"command": "syncSite",
						"site_id": this.new_theme.sites.map(s => s.site_id)
					});

					axios.post('/wp-json/captaincore/v1/sites/bulk-tools', {
						tool: 'sync-data',
						environments: envIds
					}, { headers: { 'X-WP-Nonce': this.wp_nonce } })
					.then(res => {
						const job = this.jobs.find(j => j.job_id == syncJobId);
						if (job) {
							job.job_id = res.data;
							this.runCommand(res.data);
						}
					});
				};

				this.jobs.push({
					"job_id": installJobId,
					"description": `Installing theme '${theme.name}' to ${site_count_label}`, 
					"status": "queued", 
					"stream": [],
					"on_finish": onInstallFinish
				});

				const wpcli = `wp theme install --force --skip-plugins --skip-themes '${downloadUrl}'`;

				axios.post(`/wp-json/captaincore/v1/run/code`, {
					environments: envIds,
					code: wpcli
				}, { headers: { 'X-WP-Nonce': this.wp_nonce } })
				.then(res => {
					const job = this.jobs.find(j => j.job_id == installJobId);
					if (job) {
						job.job_id = res.data;
						this.runCommand(res.data);
					}
				});

				// Clear dialog state
				this.new_theme.api.items = [];
				this.new_theme.api.info = {};
				this.new_theme.envato = { items: [], search: "" };
				this.new_theme.loading = false;
			})
			.catch(error => {
				console.log(error.response);
				this.snackbar.message = "Failed to get download link.";
				this.snackbar.show = true;
				this.new_theme.show = true;
			});
		},
		installPlugin ( plugin ) {
			const site_count_label = this.new_plugin.sites.length === 1 ? this.new_plugin.sites[0].name : `${this.new_plugin.sites.length} sites`;
			let should_proceed = confirm("Proceed with installing plugin " + plugin.name + " on " + site_count_label + "?");
			
			if ( ! should_proceed ) {
				return;
			}

			// 1. Close UI
			this.new_plugin.show = false;
			this.snackbar.message = "Installation started.";
			this.snackbar.show = true;
			this.new_plugin.api.items = [];
			this.new_plugin.api.info = {};
			this.new_plugin.loading = false;

			// 2. Determine Targets
			let envName = this.new_plugin.environment_selected || "Production";
			
			// If single site mode, check if a specific environment was active
			if ( this.new_plugin.sites.length == 1 && this.new_plugin.sites[0].environment_selected ) {
				envName = this.new_plugin.sites[0].environment_selected.environment || envName;
			}

			// Get distinct Environment IDs
			const envIds = this.getEnvironmentIdsFromSelection(this.new_plugin.sites, envName);
			
			if (envIds.length === 0) {
				this.snackbar.message = "Error: Could not determine target environments.";
				this.snackbar.show = true;
				return;
			}

			// 3. Setup Install Job
			const installDesc = `Installing plugin '${plugin.name}' to ${site_count_label}`;
			const installJobId = Math.round((new Date()).getTime());
			const wpcli = `wp plugin install --force --skip-plugins --skip-themes '${plugin.download_link}'`;

			// Define what happens after install finishes
			const onInstallFinish = () => {
				const syncDesc = `Syncing data for ${site_count_label}`;
				const syncJobId = installJobId + 1;
				
				this.jobs.push({
					"job_id": syncJobId,
					"description": syncDesc, 
					"status": "queued", 
					"stream": [],
					"command": "syncSite", 
					"site_id": this.new_plugin.sites.map(s => s.site_id) // For local refresh
				});

				axios.post('/wp-json/captaincore/v1/sites/bulk-tools', {
					tool: 'sync-data',
					environments: envIds // Send the exact same IDs we installed to
				}, {
					headers: { 'X-WP-Nonce': this.wp_nonce }
				})
				.then(response => {
					const job = this.jobs.find(j => j.job_id == syncJobId);
					if (job) {
						job.job_id = response.data;
						this.runCommand(response.data);
					}
				});
			};

			// Push Install Job
			this.jobs.push({
				"job_id": installJobId,
				"description": installDesc, 
				"status": "queued", 
				"stream": [],
				"on_finish": onInstallFinish // Attach callback
			});

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: envIds,
				code: wpcli
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				const job = this.jobs.find(j => j.job_id == installJobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand(response.data);
				}
			})
			.catch(error => {
				console.error(error);
				const job = this.jobs.find(j => j.job_id == installJobId);
				if (job) {
					job.status = 'error';
					job.stream.push("Failed to start installation: " + (error.response?.data?.message || error.message));
				}
			});
		},
		uninstallPlugin ( plugin ) {
			const site_count_label = this.new_plugin.sites.length === 1 ? this.new_plugin.sites[0].name : `${this.new_plugin.sites.length} sites`;
			let should_proceed = confirm("Proceed with uninstalling plugin " + plugin.name + " from " + site_count_label + "?");
			
			if ( ! should_proceed ) {
				return;
			}

			// 1. Close UI
			this.new_plugin.show = false;
			this.snackbar.message = "Uninstallation started.";
			this.snackbar.show = true;
			this.new_plugin.api.items = [];
			this.new_plugin.api.info = {};
			this.new_plugin.loading = false;

			// 2. Determine Targets
			let envName = this.new_plugin.environment_selected || "Production";
			
			// If single site mode, check if a specific environment was active
			if ( this.new_plugin.sites.length == 1 && this.new_plugin.sites[0].environment_selected ) {
				envName = this.new_plugin.sites[0].environment_selected.environment || envName;
			}

			// Get distinct Environment IDs
			const envIds = this.getEnvironmentIdsFromSelection(this.new_plugin.sites, envName);
			
			if (envIds.length === 0) {
				this.snackbar.message = "Error: Could not determine target environments.";
				this.snackbar.show = true;
				return;
			}

			// 3. Setup Job
			const description = `Uninstalling plugin '${plugin.name}' from ${site_count_label}`;
			const jobId = Math.round((new Date()).getTime());
			const wpcli = `wp plugin delete ${plugin.slug} --skip-themes --skip-plugins`;

			// Define what happens after finish
			const onFinish = () => {
				const syncDesc = `Syncing data for ${site_count_label}`;
				const syncJobId = jobId + 1;
				
				this.jobs.push({
					"job_id": syncJobId,
					"description": syncDesc, 
					"status": "queued", 
					"stream": [],
					"command": "syncSite", 
					"site_id": this.new_plugin.sites.map(s => s.site_id) // For local refresh
				});

				axios.post('/wp-json/captaincore/v1/sites/bulk-tools', {
					tool: 'sync-data',
					environments: envIds // Send the exact same IDs we installed to
				}, {
					headers: { 'X-WP-Nonce': this.wp_nonce }
				})
				.then(response => {
					const job = this.jobs.find(j => j.job_id == syncJobId);
					if (job) {
						job.job_id = response.data;
						this.runCommand(response.data);
					}
				});
			};

			// Push Job
			this.jobs.push({
				"job_id": jobId,
				"description": description, 
				"status": "queued", 
				"stream": [],
				"on_finish": onFinish // Attach callback
			});

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: envIds,
				code: wpcli
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand(response.data);
				}
			})
			.catch(error => {
				console.error(error);
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.status = 'error';
					job.stream.push("Failed to start uninstallation: " + (error.response?.data?.message || error.message));
				}
			});
		},
		fetchPlugins() {
			this.new_plugin.loading = true;
			site_id = this.new_plugin.sites[0].site_id
			search = this.new_plugin.search
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "fetchPlugins",
				'page': this.new_plugin.page
			};
			if ( search ) {
				data.value = search;
			}
			self = this;
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_plugin.api.items = response.data.plugins
					self.new_plugin.api.info = response.data.info
					self.new_plugin.loading = false;
				})
				.catch(error => {
					console.log(error.response)
					self.new_plugin.loading = false;
				});
		},
		fetchEnvatoThemes() {
			axios.get(
				`/wp-json/captaincore/v1/providers/envato/themes`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then( response => {
					this.new_theme.envato.items = response.data
				})
		},
		fetchEnvatoPlugins() {
			axios.get(
				`/wp-json/captaincore/v1/providers/envato/plugins`, {
					headers: {'X-WP-Nonce':this.wp_nonce}
				})
				.then( response => {
					this.new_plugin.envato.items = response.data
				})
		},
		addTheme ( site_id ) {
			site = this.dialog_site.site
			this.new_theme.show = true
			this.new_theme.sites = [ site ]
			this.new_theme.site_name = this.dialog_site.environment_selected.home_url
			this.new_theme.current_themes = this.dialog_site.environment_selected.themes.map( p => p.name )
			this.new_theme.environment_selected = this.dialog_site.environment_selected.environment
			this.fetchThemes()
			this.fetchEnvatoThemes()
		},
		addThemeBulk() {
			this.new_theme.show = true
			this.new_theme.sites = this.sites_selected
			this.new_theme.site_name = this.new_theme.sites.length + " sites"
			
			// Default to Production if multiple
			this.new_theme.environment_selected = "Production"
			
			this.fetchThemes()
			this.fetchEnvatoThemes()
		},
		installTheme ( theme ) {
			const site_count_label = this.new_theme.sites.length === 1 ? this.new_theme.sites[0].name : `${this.new_theme.sites.length} sites`;
			let should_proceed = confirm("Proceed with installing theme " + theme.name + " on " + site_count_label + "?");

			if ( ! should_proceed ) {
				return;
			}

			// 1. Close UI
			this.new_theme.show = false;
			this.snackbar.message = "Installation started.";
			this.snackbar.show = true;
			this.new_theme.api.items = [];
			this.new_theme.api.info = {};
			this.new_theme.loading = false;

			// 2. Determine Targets
			let envName = this.new_theme.environment_selected || "Production";
			if ( this.new_theme.sites.length == 1 && this.new_theme.sites[0].environment_selected ) {
				envName = this.new_theme.sites[0].environment_selected.environment || envName;
			}
			const envIds = this.getEnvironmentIdsFromSelection(this.new_theme.sites, envName);
			
			if (envIds.length === 0) return;

			// 3. JOB 1: Install Theme
			const installDesc = `Installing theme '${theme.name}' to ${site_count_label}`;
			const installJobId = Math.round((new Date()).getTime());
			const wpcli = `wp theme install '${theme.slug}' --force`;

			// Define Sync Callback
			const onInstallFinish = () => {
				const syncDesc = `Syncing data for ${site_count_label}`;
				const syncJobId = installJobId + 1;
				
				this.jobs.push({
					"job_id": syncJobId,
					"description": syncDesc, 
					"status": "queued", 
					"stream": [],
					"command": "syncSite",
					"site_id": this.new_theme.sites.map(s => s.site_id)
				});

				axios.post('/wp-json/captaincore/v1/sites/bulk-tools', {
					tool: 'sync-data',
					environments: envIds
				}, { headers: { 'X-WP-Nonce': this.wp_nonce } })
				.then(response => {
					const job = this.jobs.find(j => j.job_id == syncJobId);
					if (job) {
						job.job_id = response.data;
						this.runCommand(response.data);
					}
				});
			};

			this.jobs.push({ 
				"job_id": installJobId, 
				"description": installDesc, 
				"status": "queued", 
				"stream": [],
				"on_finish": onInstallFinish
			});

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: envIds,
				code: wpcli
			}, { headers: { 'X-WP-Nonce': this.wp_nonce } })
			.then(response => {
				const job = this.jobs.find(j => j.job_id == installJobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand(response.data);
				}
			});
		},
		uninstallTheme ( theme ) {
			const site_count_label = this.new_theme.sites.length === 1 ? this.new_theme.sites[0].name : `${this.new_theme.sites.length} sites`;
			let should_proceed = confirm("Proceed with uninstalling theme " + theme.name + " from " + site_count_label + "?");

			if ( ! should_proceed ) {
				return;
			}

			// 1. Close UI
			this.new_theme.show = false;
			this.snackbar.message = "Uninstallation started.";
			this.snackbar.show = true;
			this.new_theme.api.items = [];
			this.new_theme.api.info = {};
			this.new_theme.loading = false;

			// 2. Determine Targets
			let envName = this.new_theme.environment_selected || "Production";
			if ( this.new_theme.sites.length == 1 && this.new_theme.sites[0].environment_selected ) {
				envName = this.new_theme.sites[0].environment_selected.environment || envName;
			}
			const envIds = this.getEnvironmentIdsFromSelection(this.new_theme.sites, envName);
			
			if (envIds.length === 0) {
				this.snackbar.message = "Error: Could not determine target environments.";
				this.snackbar.show = true;
				return;
			}

			// 3. Setup Job
			const description = `Uninstalling theme '${theme.name}' from ${site_count_label}`;
			const jobId = Math.round((new Date()).getTime());
			const wpcli = `wp theme delete ${theme.slug} --skip-themes --skip-plugins`;

			// Define Sync Callback
			const onFinish = () => {
				const syncDesc = `Syncing data for ${site_count_label}`;
				const syncJobId = jobId + 1;
				
				this.jobs.push({
					"job_id": syncJobId,
					"description": syncDesc, 
					"status": "queued", 
					"stream": [],
					"command": "syncSite",
					"site_id": this.new_theme.sites.map(s => s.site_id)
				});

				axios.post('/wp-json/captaincore/v1/sites/bulk-tools', {
					tool: 'sync-data',
					environments: envIds
				}, { headers: { 'X-WP-Nonce': this.wp_nonce } })
				.then(response => {
					const job = this.jobs.find(j => j.job_id == syncJobId);
					if (job) {
						job.job_id = response.data;
						this.runCommand(response.data);
					}
				});
			};

			this.jobs.push({ 
				"job_id": jobId, 
				"description": description, 
				"status": "queued", 
				"stream": [],
				"on_finish": onFinish
			});

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: envIds,
				code: wpcli
			}, { headers: { 'X-WP-Nonce': this.wp_nonce } })
			.then(response => {
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand(response.data);
				}
			})
			.catch(error => {
				console.error(error);
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.status = 'error';
					job.stream.push("Failed to start uninstallation: " + (error.response?.data?.message || error.message));
				}
			});
		},
		fetchThemes() {
			this.new_theme.loading = true;
			site_id = this.new_theme.sites[0].site_id
			search = this.new_theme.search
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "fetchThemes",
				'page': this.new_theme.page
			};
			if ( search ) {
				data.value = search;
			}
			self = this;
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					self.new_theme.api.items = response.data.themes
					self.new_theme.api.info = response.data.info
					self.new_theme.loading = false;
				})
				.catch(error => {
					console.log(error.response)
					self.new_theme.loading = false;
				});
		},
		togglePlugin (plugin_name, plugin_status, site_id) {
			const site = this.dialog_site.site;
			const env = this.dialog_site.environment_selected;

			// Enable loading progress
			this.dialog_site.site.loading_plugins = true;
			const site_name = site.name;

			let action = "";
			if (plugin_status == "inactive") {
				action = "deactivate";
			}
			if (plugin_status == "active") {
				action = "activate";
			}

			const description = titleCase(action) + " plugin '" + plugin_name + "' from " + site_name;
			const jobId = Math.round((new Date()).getTime());
			
			// Callback to turn off loading
			const onFinish = () => {
				this.dialog_site.site.loading_plugins = false;
			};

			this.jobs.push({
				"job_id": jobId, 
				"description": description, 
				"status": "queued", 
				"stream": [], 
				"on_finish": onFinish
			});

			// WP ClI command to send
			const wpcli = `wp plugin ${action} ${plugin_name} --skip-themes --skip-plugins`;

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: [env.environment_id],
				code: wpcli
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand( response.data );
				}
			})
			.catch(error => {
				console.log(error.response);
				this.dialog_site.site.loading_plugins = false;
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.status = 'error';
					job.stream.push("Failed to start: " + (error.response?.data?.message || error.message));
				}
			});
		},
		deletePlugin (plugin_name, site_id) {
			let should_proceed = confirm("Are you sure you want to delete plugin " + plugin_name + "?");

			if ( ! should_proceed ) {
				return;
			}

			const site = this.dialog_site.site;
			const env = this.dialog_site.environment_selected;

			// Enable loading progress
			this.dialog_site.site.loading_plugins = true;

			const site_name = site.name;
			const description = "Delete plugin '" + plugin_name + "' from " + site_name;
			const jobId = Math.round((new Date()).getTime());
			
			// Callback to clean up UI
			const onFinish = () => {
				this.dialog_site.site.loading_plugins = false;
			};

			this.jobs.push({
				"job_id": jobId,
				"description": description, 
				"status": "queued", 
				"stream": [], 
				"on_finish": onFinish
			});

			// WP ClI command to send
			const wpcli = `wp plugin delete ${plugin_name} --skip-themes --skip-plugins`;

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: [env.environment_id],
				code: wpcli
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				// Optimistically update list
				const updated_plugins = env.plugins.filter(plugin => plugin.name != plugin_name);
				env.plugins = updated_plugins;
				
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand( response.data );
				}
			})
			.catch(error => {
				console.log(error.response);
				this.dialog_site.site.loading_plugins = false;
			});
		},
		runUpdate( site_id ) {

			site = this.dialog_site.site
			should_proceed = confirm("Apply all plugin/theme updates for " + site.name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// New job for progress tracking
			job_id = Math.round((new Date()).getTime());
			description = "Updating themes/plugins on " + site.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: [],"command":"update-wp", site_id: site.site_id});

			var data = {
				'action': 'captaincore_install',
				'post_id': site_id,
				'environment': this.dialog_site.environment_selected.environment,
				'command': "update-wp",
				'background': true
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					this.runCommand( response.data );
				});

		},
		killCommand( job_id ) {
			const job = this.jobs.find(j => j.job_id == job_id);
			if (job && job.conn && job.conn.readyState === WebSocket.OPEN) {
				job.conn.send( JSON.stringify({ token: job.job_id, action: "kill" }) );
				job.status = "error";
				job.stream.push("➜ Process terminated by user.");
			}
		},
		runCommand( job_id ) {
			job = this.jobs.filter(job => job.job_id == job_id)[0]
			self = this;

			job.conn = new WebSocket( this.socket );
			
			// Wait for connection to be OPEN before sending
			job.conn.onopen = () => {
				if (job.conn.readyState === WebSocket.OPEN) {
					job.conn.send( '{ "token" : "'+ job.job_id +'", "action" : "start" }' );
				}
			};
			
			job.conn.onmessage = (session) => {
				self.writeSocket(job_id, session);
				// Only auto-show console if not bulk action to prevent flickering
				if ( self.view_console.selected_targets.length <= 1 ) {
					self.view_console.show = true;
				}
				
				if (self.view_console.terminal_open) {
					setTimeout(() => {
						self.scrollToTerminalBottom();
					}, 10);
				}
			};
			job.conn.onclose = () => {
				job = self.jobs.filter(job => job.job_id == job_id)[0]
				// Safety check if job was removed
				if (!job) return;

				if ( job.stream && job.stream.length > 0 ) {
					last_output_index = job.stream.length - 1;
					last_output = job.stream[last_output_index];

					if ( last_output && last_output.trim() == "Finished.") {
						job.status = "done"
						if ( typeof job.on_finish === 'function' ) {
							job.on_finish()
						}
					} else {
						job.status = "error"
					}
				} else {
					// Assume error if stream is empty on close
					job.status = "error" 
				}

				if ( job.command == "checkSSH" ) {
					axios.get(
					'/wp-json/captaincore/v1/sites', {
						headers: {'X-WP-Nonce':this.wp_nonce}
					})
					.then( response => {
						site_updated = response.data.filter( s => s.site_id == job.site_id )
						if ( site_updated.length == 1 ) {
							this.sites = this.sites.filter( match => match.site_id != job.site_id )
							this.sites.push( site_updated[0] )
							this.sites.sort((a, b) => (a.name > b.name) ? 1 : -1)
						}
					})
				}
				
				if ( job.command == "syncSite" ) {
					self.fetchSiteInfo( job.site_id )
				}

				if ( job.command == "scanErrors" ) {
					self.fetchSiteInfo( job.site_id )
					if (self.sites.filter( s => s.site_id == job.site_id )[0]) {
						self.sites.filter( s => s.site_id == job.site_id )[0].loading = false
					}
				}

				if ( job.command == "manage" && job.environment ) {
					self.syncSiteEnvironment( job.site_id, job.environment );
				}

				if ( job.command == "manage" && !job.environment ) {
					self.syncSiteEnvironment( job.site_id, "Production" );
				}

				if ( job.command == "update-wp" ){
					this.viewUpdateLogs( job.site_id );
				}
			}
		},
		writeSocket( job_id, session ) {
			job = self.jobs.filter(job => job.job_id == job_id)[0]
			job.stream.push( session.data )
		},
		configureFathom( site_id ) {
			site = this.dialog_site.site
			this.dialog_fathom.site = site
			this.dialog_fathom.environment = this.dialog_site.environment_selected;
			this.dialog_fathom.show = true;
		},
		configureFathomClose() {
			this.dialog_fathom.editItem = false;
			setTimeout(() => {
				this.dialog_fathom.editedItem = {}
				this.dialog_fathom.editedIndex = -1
			}, 300)
		},
		configureFathomSave() {
			if (this.dialog_fathom.editedIndex > -1) {
          		Object.assign(this.dialog_fathom.environment.fathom[this.dialog_fathom.editedIndex], this.dialog_fathom.editedItem)
			} else {
				this.dialog_fathom.environment.fathom.push(this.dialog_fathom.editedItem)
			}
			this.configureFathomClose()
		},
		newFathomItem(){
			this.dialog_fathom.environment.fathom.push({ "code": "", "domain" : "" })
		},
		deleteFathomLiteItem (item) {
			const index = this.dialog_fathom.environment.fathom.indexOf(item)
			confirm('Are you sure you want to delete this item?') && this.dialog_fathom.environment.fathom.splice(index, 1)
		},
		deleteFathomItem (item) {
			const index = this.dialog_site.environment_selected.fathom_analytics.indexOf(item)
			confirm('Are you sure you want to delete this item?') && this.dialog_site.environment_selected.fathom_analytics.splice(index, 1)
		},
		saveMailgun() {
			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': this.dialog_site.site.site_id,
				'command': "updateMailgun",
				'value': this.dialog_site.site.mailgun,
			};
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// close dialog
					this.dialog_mailgun_config.show = false;
				});
		},
		fetchMailgunDetails() {
			// Reset state
			this.mailgun.data = null;
			this.mailgun.loading = true;

			// Check if the CaptainCore domain object and its ID exist
			if (this.dialog_domain && this.dialog_domain.domain && this.dialog_domain.domain.domain_id) {
				
				const domain_id = this.dialog_domain.domain.domain_id;

				axios.get(`/wp-json/captaincore/v1/domain/${domain_id}/mailgun`, {
					headers: { 'X-WP-Nonce': this.wp_nonce }
				})
				.then(response => {
					this.mailgun.data = response.data;
				})
				.catch(error => {
					console.error("Error fetching Mailgun details:", error);
					// Check for the specific "not configured" error
					if (error.response && error.response.data && error.response.data.code === 'mailgun_not_configured') {
						// This is an expected state, not an "error" to show.
						// The UI will show the "Setup Mailgun" button.
					} else {
						// Show other, unexpected errors
						this.snackbar.message = "Error fetching Mailgun details: " + (error.response?.data?.message || error.message);
						this.snackbar.show = true;
					}
				})
				.finally(() => {
					this.mailgun.loading = false;
				});

			} else {
				// No domain ID found, so don't fetch anything
				this.mailgun.loading = false;
			}
		},
		showMailgunActivatePrompt(domain) {
			this.mailgun.activeDomain = domain;
			this.mailgun.subdomain = 'mg';
			this.mailgun.subdomainDialog = true;
		},
		async submitMailgunActivation() {
			this.mailgun.subdomainDialog = false;
			this.mailgun.loadingActivate = true;
			const domain_id = this.dialog_domain.domain.domain_id;
			const fullDomain = `${this.mailgun.subdomain}.${this.mailgun.activeDomain.name}`;
			
			try {
				// 1. Make the POST request and *capture the response*
				const response = await axios.post(`/wp-json/captaincore/v1/domain/${domain_id}/mailgun/setup`, { 
					domain_id: this.mailgun.activeDomain.domain_id, 
					domain: fullDomain 
				}, {
					headers: { 'X-WP-Nonce': this.wp_nonce }
				});

				// 2. Now that details are fresh, fetch content for the new tab
				this.fetchMailgunDetails(); // This populates this.mailgun.data for the tab content

				// 3. This refetches DNS records for the DNS tab
				this.modifyDNS( this.dialog_domain.domain );

				// 4. Use the fresh data from the response to update the local state
				if (response.data.domain && response.data.domain.details) {
					this.dialog_domain.accounts = response.data.domain.accounts; 
					this.dialog_domain.connected_sites = response.data.domain.connected_sites; 
					this.dialog_domain.details = response.data.domain.details; 
				} else {
					// Fallback if the response format is wrong
					throw new Error("Activation response did not include updated domain data.");
				}

				// 5. Switch to the new tab
				this.dialog_domain.tabs = 'mailgun';

				// Show success message
				this.snackbar.message = `Mailgun zone ${fullDomain} created successfully.`;
				this.snackbar.show = true;

				} catch (error) {
					console.error("Error activating Mailgun:", error)
					this.snackbar.message = "Error activating Mailgun: " + (error.response?.data?.message || error.message);
					this.snackbar.show = true;
				} finally {
					// This runs after try or catch, ensuring the loader always stops
					this.mailgun.loadingActivate = false;
					this.dialog_domain.loading = false;
				}
		},
		verifyMailgunDomain(domain) {
			this.mailgun.loadingVerify = true;
			const domain_id = this.dialog_domain.domain.domain_id;

			// 1. Updated endpoint to include domain_id
			axios.post(`/wp-json/captaincore/v1/domain/${domain_id}/mailgun/verify`, { 
					domain: this.mailgun.data.domain.name // Send the mailgun zone name
				}, {
					headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
                this.mailgun.loadingVerify = false;

                // 2. Update local data instead of reloading
				if (response.data && response.data.domain) {
                    // Update the domain state
					this.mailgun.data.domain.state = response.data.domain.state;

                    // Update the DNS record statuses
                    if (response.data.sending_dns_records) {
                        this.mailgun.data.sending_dns_records = response.data.sending_dns_records;
                    }
                    if (response.data.receiving_dns_records) {
                        this.mailgun.data.receiving_dns_records = response.data.receiving_dns_records;
                    }

                    // 3. Show snackbar feedback
					if (response.data.domain.state === 'active') {
						this.snackbar.message = "Domain verified successfully!";
						this.snackbar.show = true;
					} else {
						this.snackbar.message = "Verification check complete. Domain is not yet verified.";
						this.snackbar.show = true;
					}
				} else {
                    // Fallback if response is unexpected
					this.snackbar.message = "Verification check complete.";
					this.snackbar.show = true;
                }
			})
			.catch(error => {
				console.error("Error verifying Mailgun domain:", error)
				this.mailgun.loadingVerify = false;
                this.snackbar.message = "Error during verification: " + (error.response?.data?.message || error.message);
                this.snackbar.show = true;
			});
		},
		copyMailgunRecordsToClipboard() {
			if (!this.mailgun.data || !this.dialog_domain.details.mailgun_zone) {
				this.snackbar.message = "No records to copy.";
				this.snackbar.show = true;
				return;
			}

			let recordsText = `Mailgun DNS Records for ${this.dialog_domain.domain.name}:\n\n`;

			if (this.mailgun.data.sending_dns_records && this.mailgun.data.sending_dns_records.length > 0) {
				this.mailgun.data.sending_dns_records.forEach(record => {
					recordsText += `Type: ${record.record_type}\n`;
					recordsText += `Name: ${record.name}\n`;
					recordsText += `Value: ${record.value}\n\n`;
				});
			}

			if (this.mailgun.data.receiving_dns_records && this.mailgun.data.receiving_dns_records.length > 0) {
				this.mailgun.data.receiving_dns_records.forEach(record => {
					recordsText += `Type: ${record.record_type}\n`;
					recordsText += `Name: ${record.name}\n`;
					recordsText += `Priority: ${record.priority}\n`;
					recordsText += `Value: ${record.value}\n\n`;
				});
			}

			this.copyText(recordsText);
			this.snackbar.message = "DNS records copied to clipboard.";
			this.snackbar.show = true;
		},
		showMailgunDeployPrompt(site, domain) {
			this.mailgun.activeSite = site;
			this.mailgun.activeDomain = domain;
			this.mailgun.deployName = '';
			this.mailgun.deployDialog = true;
		},
		submitMailgunDeploy() {
			// First, validate the form
			this.$refs.mailgunDeployForm.validate();

			// Check if the form is valid
			if (!this.mailgun.deployFormValid) {
				this.snackbar.message = "Please fill in all required fields.";
				this.snackbar.show = true;
				return; // Stop execution if form is invalid
			}

			// Form is valid, proceed
			this.mailgun.deployDialog = false;
			this.mailgun.loadingDeploy = true; // <-- Set loading to true
			const domain_id = this.dialog_domain.domain.domain_id;
			axios.post(`/wp-json/captaincore/v1/domain/${domain_id}/mailgun/deploy`, {
				site_id: this.mailgun.activeSite.id,
				environment: this.mailgun.activeSite.environment,
				domain: this.dialog_domain.details.mailgun_zone.name,
				from_name: this.mailgun.deployName
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				// Show success message
				this.snackbar.message = "Mailgun deployment completed successfully.";
				this.snackbar.show = true;
			})
			.catch(error => {
				this.snackbar.message = "Error deploying Mailgun: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
			})
			.finally(() => {
				this.mailgun.loadingDeploy = false;
			});
		},
		openMailgunDeployDialog() {
            this.dialog_mailgun_deploy.search = '';
            this.dialog_mailgun_deploy.currentPage = 1;
            this.dialog_mailgun_deploy.show = true;
        },
		confirmDeleteMailgunZone(domain) {
			if (!confirm(`Are you sure you want to delete the Mailgun zone "${this.dialog_domain.details.mailgun_zone}"? This will stop email services configured through Mailgun but will not delete your DNS records.`)) {
				return;
			}
			this.deleteMailgunZone(domain);
		},
		deleteMailgunZone(domain) {
			this.mailgun.loadingActivate = true; // Use the same loader as activation
			const domain_id = domain.domain_id;

			axios.delete(`/wp-json/captaincore/v1/domain/${domain_id}/mailgun`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				this.snackbar.message = response.data.message || "Mailgun zone deleted successfully.";
				this.snackbar.show = true;
				
				// Update the local domain details to hide the button and tab
				if (response.data.domain && response.data.domain.details) {
					this.dialog_domain.details = response.data.domain.details;
				} else {
					// Fallback: manually clear local data
					this.dialog_domain.details.mailgun_id = null;
					this.dialog_domain.details.mailgun_zone = null;
					this.dialog_domain.details.mailgun_smtp_password = null;
				}
				
				// Switch back to the DNS tab if we were on the Mailgun tab
				if (this.dialog_domain.tabs === 'mailgun') {
					this.dialog_domain.tabs = 'dns';
				}
				// Clear mailgun data
				this.mailgun.data = null;
			})
			.catch(error => {
				this.snackbar.message = "Error deleting Mailgun zone: " + (error.response?.data?.message || error.message);
				this.snackbar.show = true;
			})
			.finally(() => {
				this.mailgun.loadingActivate = false;
			});
		},
		saveFathomConfigurations() {
			site = this.dialog_fathom.site;
			environment = this.dialog_fathom.environment;
			site_id = site.site_id;
			should_proceed = confirm("Apply new Fathom tracker for " + site.name + "?");

			if ( ! should_proceed ) {
				return;
			}

			// New job for progress tracking
			job_id = Math.round((new Date()).getTime());
			description = "Updating Fathom tracker on " + site.name;
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: []});

			environment.fathom.forEach( fathom => {
				fathom.domain = fathom.domain.trim()
				fathom.code = fathom.code.trim()
			})

			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site_id,
				'command': "updateFathom",
				'environment': this.dialog_site.environment_selected.environment,
				'value': {
					fathom_lite: environment.fathom,
					fathom: this.dialog_site.environment_selected.fathom_analytics
				}
			}

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					// close dialog
					this.dialog_fathom.site = {};
					this.dialog_fathom.show = false;
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					this.runCommand( response.data );
				});
		},
		verifyAndPayInvoice( invoice_id ) {
			this.billing.rules = { 
				firstname: [v => !!v || 'First Name is required'],
				lastname: [v => !!v || 'Last Name is required'],
				address_1: [v => !!v || 'Address line 1 is required'],
				city: [v => !!v || 'City is required'],
				state: [v => !!v || 'State is required'],
				zip: [v => !!v || 'Zip is required'],
				email: [v => !!v || 'Email is required'],
				country: [v => !!v || 'Country is required']
			}
			this.$nextTick(() => {
				this.payInvoice( invoice_id )
        	})
		},
		updateBilling() {
			var data = {
				'action': 'captaincore_account',
				'command': "updateBilling",
				'value': this.billing.address,
			};

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					this.fetchBilling()
					this.snackbar.message = "Billing infomation updated."
					this.snackbar.show = true
				});
		},
		updateSettings() {
			this.dialog_update_settings.show = true;
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected
			this.dialog_update_settings.environment.updates_exclude_plugins = environment.updates_exclude_plugins
			this.dialog_update_settings.environment.updates_exclude_themes = environment.updates_exclude_themes
			this.dialog_update_settings.environment.updates_enabled = environment.updates_enabled
			this.dialog_update_settings.themes = environment.themes
			this.dialog_update_settings.plugins = environment.plugins
		},
		toggleMonitor( environment ) {
			status = "OFF"
			if ( environment.monitor_enabled == 1 ) {
				status = "ON"
			}
			axios.post( `/wp-json/captaincore/v1/sites/${environment.site_id}/${environment.environment.toLowerCase()}/monitor`, {
				monitor: environment.monitor_enabled
			}, {
				headers: { 'X-WP-Nonce':this.wp_nonce }
			})
			.then( response => {
				this.snackbar.message = `Toggling monitor for ${environment.home_url} ${status}.`
				this.snackbar.show = true
			})
		},
		saveUpdateSettings() {
			this.dialog_update_settings.loading = true;
			site = this.dialog_site.site

			// Adds new job
			job_id = Math.round((new Date()).getTime());
			description = "Saving update settings for " + site.name + " (" + this.dialog_site.environment_selected.environment + ")";
			this.jobs.push({"job_id": job_id,"description": description, "status": "queued", stream: [], "command":"saveUpdateSettings"});

			// Prep AJAX request
			var data = {
				'action': 'captaincore_ajax',
				'post_id': site.site_id,
				'command': "updateSettings",
				'environment': this.dialog_site.environment_selected.environment,
				'value': { 
					"updates_exclude_plugins": this.dialog_update_settings.environment.updates_exclude_plugins, 
					"updates_exclude_themes": this.dialog_update_settings.environment.updates_exclude_themes, 
					"updates_enabled": this.dialog_update_settings.environment.updates_enabled
					}
			};

			this.dialog_update_settings.show = false;
			this.dialog_update_settings.loading = false;

			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
					environment = this.dialog_site.environment_selected;
					environment.updates_exclude_plugins = this.dialog_update_settings.environment.updates_exclude_plugins;
					environment.updates_exclude_themes = this.dialog_update_settings.environment.updates_exclude_themes;
					environment.updates_enabled = this.dialog_update_settings.environment.updates_enabled;
					this.jobs.filter(job => job.job_id == job_id)[0].job_id = response.data;
					this.runCommand( response.data );
				});

		},
		deleteUserDialog( username, site_id ){
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected;
			this.dialog_delete_user.username = username
			this.dialog_delete_user.site = site
			this.dialog_delete_user.show = true
			this.dialog_delete_user.users = environment.users.filter( u => u.user_login != username )
		},
		deleteUser() {
			if ( this.dialog_delete_user.reassign.ID == undefined ) {
				this.snackbar.message = "Can't delete user without reassign content to another user.";
				this.snackbar.show = true;
				return;
			}
			const username = this.dialog_delete_user.username;
			const site = this.dialog_delete_user.site;
			const env = this.dialog_site.environment_selected;
			
			let should_proceed = confirm("Are you sure you want to delete user " + username + "?");

			if ( ! should_proceed ) {
				return;
			}
			
			const site_name = site.name;
			const description = "Delete user '" + username + "' from " + site_name + " (" + env.environment + ")";
			const jobId = Math.round((new Date()).getTime());
			
			this.jobs.push({
				"job_id": jobId,
				"description": description, 
				"status": "queued", 
				"stream": []
			});

			// WP ClI command to send
			const wpcli = `wp user delete ${username} --reassign=${this.dialog_delete_user.reassign.ID} --skip-themes --skip-plugins`;

			axios.post(`/wp-json/captaincore/v1/run/code`, {
				environments: [env.environment_id],
				code: wpcli
			}, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then( response => {
				env.users = env.users.filter(user => user.username != username);
				
				const job = this.jobs.find(j => j.job_id == jobId);
				if (job) {
					job.job_id = response.data;
					this.runCommand( response.data );
				}
				
				this.dialog_delete_user.show = false;
				this.dialog_delete_user.site = {};
				this.dialog_delete_user.reassign = {};
				this.dialog_delete_user.username = "";
				this.dialog_delete_user.users = [];
			});
		},
		bulkactionLaunch() {
			// If the terminal is open, we should use the targets selected in the console directly
			if (this.view_console.terminal_open && this.view_console.selected_targets.length > 0) {
				this.view_console.selected_targets.forEach(target => {
					if (target.home_url) {
						// Ensure URL has a protocol
						const url = target.home_url.startsWith('http') ? target.home_url : `https://${target.home_url}`;
						window.open(url);
					}
				});
				return;
			}

			// Default behavior for the main Sites list bulk tools
			if (this.dialog_bulk_tools.environment_selected == "Production" || this.dialog_bulk_tools.environment_selected == "Both") {
				this.sites_selected.forEach(site => {
					if (site.environments && site.environments[0] && site.environments[0].home_url) {
						window.open(site.environments[0].home_url);
					}
				});
			}
			if (this.dialog_bulk_tools.environment_selected == "Staging" || this.dialog_bulk_tools.environment_selected == "Both") {
				this.sites_selected.forEach(site => {
					// Find the staging environment specifically
					const staging = site.environments ? site.environments.find(e => e.environment === "Staging") : null;
					if (staging && staging.home_url) {
						window.open(staging.home_url);
					}
				});
			}
		},
		keepTimestampNextRun: function ( date, item ) {
			if ( typeof item.next_run == 'undefined' ) {
				today = new Date().getFullYear()+'-'+("0"+(new Date().getMonth()+1)).slice(-2)+'-'+("0"+new Date().getDate()).slice(-2)
				item.next_run = `${today} 5:00:00`
			} else if ( item.next_run == "" ) {
				item.next_run = `${date} 5:00:00`
			} else {
				timestamp = item.next_run.split(" ")[1]
				item.next_run = `${date} ${timestamp}`
			}
		},
		keepTimestamp: function ( newDate ) {
			// If the user clears the date, newDate will be null.
			if (!newDate) {
				this.dialog_modify_plan.plan.next_renewal = '';
				this.dialog_modify_plan.date_selector = false;
				return;
			}

			let timePart = '05:00:00';
			const currentRenewal = this.dialog_modify_plan.plan.next_renewal;

			// Check if there is an existing valid time part in the model to preserve it
			if (currentRenewal && typeof currentRenewal === 'string' && currentRenewal.includes(' ')) {
				const parts = currentRenewal.split(' ');
				if (parts.length > 1 && parts[1].match(/^\d{2}:\d{2}:\d{2}$/)) {
					timePart = parts[1];
				}
			}

			// Format the new date from the picker (which is a Date object)
			const datePart = dayjs(newDate).format('YYYY-MM-DD');

			// Combine the new date with the preserved time and update the model
			this.dialog_modify_plan.plan.next_renewal = `${datePart} ${timePart}`;
			
			// Close the date picker menu
			this.dialog_modify_plan.date_selector = false;
		},
		previewCode ( text ) {
			maxLength = 40
			if (text.length > maxLength) {
				return text.substring(0, maxLength) + '...';
			}
			return text;
		},
		intervalLabel ( interval ) {
			units = [] 
			units[1] = "monthly"
			units[3] = "quarterly"
			units[6] = "biannually"
			units[12] = "yearly"
			return units[ interval ]
		},
		safeUrl ( url ) {
			return url.replaceAll( '#', '%23' )
		},
		timeago ( timestamp ){
			return moment.utc( timestamp, "YYYY-MM-DD hh:mm:ss").fromNow();
		},
		formatTime ( value ) {
			var sec_num = parseInt(value, 10); // don't forget the second param
			var hours   = Math.floor(sec_num / 3600);
			var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
			var seconds = sec_num - (hours * 3600) - (minutes * 60);

			if (hours   < 10) {hours   = "0"+hours;}
			if (minutes < 10) {minutes = "0"+minutes;}
			if (seconds < 10) {seconds = "0"+seconds;}
			return minutes + ':' + seconds;
		},
		formatProvider (value) {
			if (value == 'wpengine') {
				return "WP Engine"
			}
			if (value == 'kinsta') {
				return "Kinsta"
			}
			if (value == 'rocketdotnet') {
				return "Rocket.net"
			}
		},
		formatSize (fileSizeInBytes) {
			var i = -1;
			var byteUnits = [' kB', ' MB', ' GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];
			do {
				fileSizeInBytes = fileSizeInBytes / 1024;
				i++;
			} while (fileSizeInBytes > 1024);
    		return Math.max(fileSizeInBytes, 0.1).toFixed(1) + byteUnits[i];
		},
		formatGBs (fileSizeInBytes) {
			fileSizeInBytes = fileSizeInBytes / 1024 / 1024 / 1024;
			return Math.max(fileSizeInBytes, 0.1).toFixed(2);
		},
		formatLargeNumbers (number) {
			if ( isNaN(number) || number == null ) {
				return null;
			} else {
				return number.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
			}
		},
		formatk (num) {
			if (num < 9999 ) {
				return numeral(num).format('0,0');
			}
			if (num < 99999 ) {
				return numeral(num).format('0.0a');
			}
			if (num < 999999 ) {
				return numeral(num).format('0a');
			}
			return numeral(num).format('0.00a');
		},
		formatPercentage (percentage) {
			return Math.max(percentage, 0.1).toFixed(0);
		},
		formatPercentageFixed (percentage) {
			return (Math.max(percentage, 0.1) * 100 ).toFixed(2) + '%';
		},
		account_storage_percentage ( account ) {
			percentage = ( account.plan.usage.storage / ( account.plan.limits.storage * 1024 * 1024 * 1024 ) ) * 100
			percentage_formatted = Math.max(percentage, 0.1).toFixed(0)
			results = `<small>${percentage_formatted}</small>`
			if ( percentage >= 100 ) {
				results = `<i aria-hidden="true" class="v-icon notranslate mdi mdi-check"></i>`
			}
			return results
		},
		account_visits_percentage ( account ) {
			percentage = ( account.plan.usage.visits / account.plan.limits.visits ) * 100
			percentage_formatted = Math.max(percentage, 0.1).toFixed(0)
			results = `<small>${percentage_formatted}</small>`
			if ( percentage >= 100 ) {
				results = `<i aria-hidden="true" class="v-icon notranslate mdi mdi-check"></i>`
			}
			return results
		},
		account_site_percentage ( account ) {
			percentage = account.plan.usage.sites / account.plan.limits.sites * 100
			percentage_formatted = Math.max(percentage, 0.1).toFixed(0)
			results = `<small>${percentage_formatted}</small>`
			if ( percentage >= 100 ) {
				results = `<i aria-hidden="true" class="v-icon notranslate mdi mdi-check"></i>`
			}
			return results
		},
		pretty_timestamp (date) {
			// takes in '2018-06-18 19:44:47' then returns "Monday, Jun 18, 2018, 7:44 PM"
			formatted_date = new Date(date).toLocaleTimeString("en-us", pretty_timestamp_options);
			return formatted_date;
		},
		pretty_timestamp_short (date) {
			// takes in '2018-06-18 19:44:47' then returns "Monday, Jun 18, 2018, 7:44 PM"
			formatted_date = new Date(date).toLocaleDateString("en-us", {
				year: "numeric", month: "long", day: "numeric"
			})
			return formatted_date
		},
		pretty_timestamp_epoch (date) {
			// takes in '1577584719' then returns "Monday, Jun 18, 2018, 7:44 PM"
			d = new Date(0);
			d.setUTCSeconds(date);
			formatted_date = d.toLocaleTimeString("en-us", pretty_timestamp_options);
			return formatted_date;
		},
		filterFiles( site_id, hash ) {
			site = this.dialog_site.site
			environment = this.dialog_site.environment_selected;
			quicksave = environment.quicksaves.filter( quicksave => quicksave.hash == hash )[0];
			search = quicksave.search;
			quicksave.filtered_files = quicksave.view_files.filter( file => file.includes( search ) );
		},
		clearSiteFilters() {
			this.search = '';
			this.isUnassignedFilterActive = false;
			this.applied_theme_filters = [];
			this.applied_plugin_filters = [];
			this.applied_core_filters = [];
			this.filtered_environment_ids = []; // Clear server results
			
			// Manually reset all sites to be visible.
			this.sites.forEach(site => {
				site.filtered = true;
			});

			this.snackbar.message = "Filters cleared.";
			this.snackbar.show = true;
		},
		toggleUnassignedFilter() {
			this.isUnassignedFilterActive = !this.isUnassignedFilterActive;
		},
		applySiteFilters() {
			this.sites.forEach(site => {
				// This logic assumes only one filter type (unassigned) sets the flag.
				// If advanced filters are also used, they will override this.
				if (this.isUnassignedFilterActive) {
					site.filtered = site.account_id === "" || site.account_id === "0";
				} else {
					// If the unassigned filter is off, we must not interfere with the advanced filter.
					// A more complex integration is needed if they must work together.
					// For now, this assumes they are used separately.
					if (!this.applied_site_filter.length > 0) {
						site.filtered = true;
					}
				}
			});
		},
		toggleEmptyFilter() {
			this.isEmptyFilterActive = !this.isEmptyFilterActive;
			this.applyAccountFilters();
		},
		clearAccountFilters() {
			this.account_search = '';
			this.isOutstandingFilterActive = false;
			this.isEmptyFilterActive = false;
			this.applyAccountFilters();
			this.snackbar.message = "Filters cleared.";
			this.snackbar.show = true;
		},
		toggleOutstandingFilter() {
			this.isOutstandingFilterActive = !this.isOutstandingFilterActive;
			this.applyAccountFilters();
		},
		applyAccountFilters() {
			this.accounts.forEach(account => {
				let passesFilter = true;

				if (this.isOutstandingFilterActive) {
					passesFilter = passesFilter && (account.metrics.outstanding_invoices && account.metrics.outstanding_invoices > 0);
				}

				if (this.isEmptyFilterActive) {
					passesFilter = passesFilter && (account.metrics.users === 0 && account.metrics.sites === 0 && account.metrics.domains === 0);
				}

				const searchLower = this.account_search ? this.account_search.toLowerCase() : '';
				if (searchLower) {
					const nameMatch = account.name && account.name.toLowerCase().includes(searchLower);
					passesFilter = passesFilter && nameMatch;
				}

				account.filtered = passesFilter;
			});
		},
		updatePrimaryFilters(type, value) {
			// This method ensures new filter items have properties to hold their selections
			value.forEach(item => {
				if (!item.selected_versions) item.selected_versions = [];
				if (!item.selected_statuses) item.selected_statuses = [];
			});

			if (type === 'themes') {
				this.applied_theme_filters = value;
				this.themeFilterMenu = false;
			} else if (type === 'plugins') {
				this.applied_plugin_filters = value;
				this.pluginFilterMenu = false;
			} else if (type === 'core') {
				this.applied_core_filters = value;
				this.coreFilterMenu = false;
			}
			this.filterSites();
		},
		getVersionsForFilter(filterName) {
			if (!this.site_filter_version) return [];
			const filterData = this.site_filter_version.find(f => f && f.name === filterName);
			return filterData ? filterData.versions : [];
		},
		getStatusesForFilter(filterName) {
			if (!this.site_filter_status) return [];
			const filterData = this.site_filter_status.find(f => f && f.name === filterName);
			return filterData ? filterData.statuses : [];
		},
		filterSites() {
			// If no advanced filters are selected, reset everything
			if (this.combinedAppliedFilters.length === 0 && this.applied_core_filters.length === 0) {
				// Make all sites visible locally
				this.sites.forEach(s => s.filtered = true);
				this.filtered_environment_ids = [];
				return;
			}

			this.sites_loading = true; // Optional visual feedback

			// Consolidate selected versions and statuses from individual filters
			const allSelectedVersions = this.combinedAppliedFilters.flatMap(filter => filter.selected_versions || []);
			const allSelectedStatuses = this.combinedAppliedFilters.flatMap(filter => filter.selected_statuses || []);

			// Construct the filter object for the backend
			const filters = {
				logic: this.filter_logic,
				version_logic: this.filter_version_logic,
				status_logic: this.filter_status_logic,
				themes: this.applied_theme_filters.map( ({ name, title, search, type }) => ({ name, title, search, type }) ),
				plugins: this.applied_plugin_filters.map( ({ name, title, search, type }) => ({ name, title, search, type }) ),
				core: this.applied_core_filters.map( ({ name }) => name ),
				versions: allSelectedVersions,
				statuses: allSelectedStatuses,
			};

			axios.post('/wp-json/captaincore/v1/filters/sites', filters, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(response => {
				const results = response.data.results || [];
				
				// 1. Store matching environment IDs for isEnvironmentMatched()
				this.filtered_environment_ids = results.map(r => r.environment_id);
				
				// 2. Update site visibility based on whether they have ANY matching environment
				const matchingSiteIds = new Set(results.map(r => r.site_id));
				
				this.sites.forEach(s => {
					s.filtered = matchingSiteIds.has(s.site_id);
				});
			})
			.catch(error => {
				console.error("Error fetching filtered sites:", error);
			})
			.finally(() => {
				this.sites_loading = false;
				this.page = 1;
			});
		},
	}
});

app.use(vuetify);
app.component('file-upload', VueUploadComponent);
app.mount('#app');

</script>
<?php if ( is_plugin_active( 'arve-pro/arve-pro.php' ) ) { ?>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script type='text/javascript' src='/wp-content/plugins/arve-pro/build/main.js'></script>
<script type='text/javascript' src='/wp-content/plugins/advanced-responsive-video-embedder/build/main.js'></script>
<?php } ?>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type", content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width initial-scale=1 maximum-scale=1">
        <meta name='apple-mobile-web-app-capable' content='yes'>
        <meta name='apple-mobile-web-app-status-bar-style' content='black'>
        <title>Showcase - Vue</title>

        <!-- Stylesheet and head scripts go here -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" type="text/css" href="<?php echo scripts('css'); ?>" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css';?>">
        
    </head>
    
    <body>
        <!-- Beginning of the app -->
        <div id="app" v-cloak>
            <div class="container">
                <div class="spacer-4"></div>
                <h2 class="medium-text horizontal-center semibold">My Projects</h2>
                <p class="horizontal-center">The Showcase projects, rendered by Vue in the browser</p>
                <div class="spacer-2"></div>
                <div class="row">
                    <div class="col-md-6 col-lg-4 mb-4" v-for="project in projects" :key="project.id">
                        <div class="card color0 equally-spaced-1 dropshadow">
                            <h2 class="medium-text semibold textcolor5 reset-margin">{{ project.title }}</h2>
                            <p class="textcolor5 default-text">{{ project.description }}</p>
                            <button class="btn btn-outline-dark btn-sm" @click="remove(project)">delete</button>
                        </div>
                    </div>
                </div>
                <div class="card color0 equally-spaced-1 dropshadow width-max-600 horizontal-center">
                    <form @submit.prevent="add">
                        <div class="mb-3">
                            <label class="default-text textcolor5" for="title">* title</label>
                            <input id="title" class="form-control input-default" v-model="form.title" maxlength="150">
                            <div class="text-danger small-text" v-if="errors.title">{{ errors.title }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="default-text textcolor5" for="description">* description</label>
                            <textarea id="description" class="form-control input-default" rows="3" v-model="form.description"></textarea>
                            <div class="text-danger small-text" v-if="errors.description">{{ errors.description }}</div>
                        </div>
                        <input class="btn btn-secondary w-100" type="submit" value="add a project">
                    </form>
                </div>
                <div class="spacer-4"></div>
            </div>
        </div>
		
		
		<!-- TEMPLATES FOR VUE COMPONENTS -->
	
		<!--
		<script type='text/x-template' id='template'>
			<div>
				<!-- code here
			</div>	
		</script>
		-->
        
        <!-- Initialization scripts -->
        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo scripts('axios'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <script> 
            /* Loading the app client framework
             * Any environment variables to be passed on from the server can take place in this here
             */
            loadVue({
                scripts: ['main']
             });
        </script>
        
        <!-- Additional scripts go here -->
        <script src="<?php echo scripts('bootstrap_script'); ?>"></script>
        <script src="<?php echo scripts('script'); ?>"></script>
        
    </body>
</html>
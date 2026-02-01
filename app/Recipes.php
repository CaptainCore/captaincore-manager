<?php 

namespace CaptainCore;

class Recipes extends DB {

	static $primary_key = 'recipe_id';
	
	public function list() {
		$user        = new User;
		$user_id     = get_current_user_id();
		$recipes     = [];
		$all_recipes = self::fetch_recipes();

        // Bail if not assigned a role
        if ( ! $user->role_check() ) {
            return 'Error: Please log in.';
        }

        foreach( $all_recipes as $recipe ) {
			// Remove details if not and admin and record not owned by them
			if ( ! $user->is_admin() && $recipe->user_id != $user_id ) {
				$recipe->content = "";
				$recipe->user_id = "system";
			}
            
            unset( $recipe->updated_at );
            unset( $recipe->created_at );
            $recipes[] = $recipe;
        }
        usort($recipes, function($a, $b) { return strcmp($a->title, $b->title); });
        return $recipes;
    }

    public function verify( $recipe_id = "" ) {
        $user    = new User;
        $user_id = get_current_user_id();

        // Admins can access all recipes
        if ( $user->is_admin() ) {
            return true;
        }

        // Check multiple recipe ids
        if ( is_array( $recipe_id ) ) {
            foreach ( $recipe_id as $id ) {
                $recipe = self::get( $id );
                if ( ! $recipe || $recipe->user_id != $user_id ) {
                    return false;
                }
            }
            return true;
        }

        // Check individual recipe id
        $recipe = self::get( $recipe_id );
        if ( $recipe && $recipe->user_id == $user_id ) {
            return true;
        }

        return false;
    }


}
<?php

if(!class_exists('WP_CLI')) {
	return;
}

// force core loading importers
define('WP_LOAD_IMPORTERS', true);

// class definition
WP_CLI::add_hook('after_add_command:import', function ()
{
	class Import_Plus extends WP_CLI_Command
	{
		private $term_taxonomy = 'post_tag';
		private $terms = array();
		private $post_metas = array();

		/**
		 * Allow to associate extra terms and post meta to each post imported via a WXR file generated by the Wordpress export feature.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Path to a valid WXR files for importing. Directories are also accepted..
		 *
		 * [--authors=<authors>]
		 * : How the author mapping should be handled. Options are ‘create’, ‘mapping.csv’, or ‘skip’. The first will create any
		 *   non-existent users from the WXR file. The second will read author mapping associations from a CSV, or create a CSV
		 *   for editing if the file path doesn’t exist. The CSV requires two columns, and a header row like “old_user_login,new_user_login”.
		 *   The last option will skip any author mapping.
		 *
		 * [--skip=<data-type>]
		 * : Skip importing specific data. Supported options are: ‘attachment’ and ‘image_resize’ (skip time-consuming thumbnail generation).
		 *
		 * [--extra-terms-taxonomy=<taxonomy-name>]
		 * : The taxonomy of the extra terms to associate to each imported post. Default "post_tag".
		 *
		 * [--extra-terms=<slugs>]
		 * : Comma-separated list of terms to associate to each imported post. If you want to enter terms of a hierarchical taxonomy like
		 * 	categories, then use IDs. If you want to add non-hierarchical terms like tags, then use names.
		 *
		 * [--extra-post-meta-keys=<post-meta-keys>]
		 * : Comma-separated list of post-meta keys to associate to each imported post.
		 *
		 * [--extra-post-meta-values=<post-meta-values>]
		 * : Comma-separated list of post-meta value to associate to each imported post.
		 * 	The values will be assigned respectively in the same order to the keys specified in --extra-post-meta-keys.
		 *
		 * ---
		 * default: success
		 * options:
		 *   - success
		 *   - error
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp import-plus --extra-terms-taxonomy=category --extra-terms=imported-posts --extra-post-meta-keys=imported_post,custom_meta --extra-post-meta-values=yes,example export.xml
		 *
		 * @when after_wp_load
		 */
		public function __invoke($args, $assoc_args)
		{
			// recupero dati extra
			$this->orderTerms($assoc_args);
			$this->orderPostMetas($assoc_args);

			// hook on saved post
			add_action('save_post', array($this, 'associateExtraData'), 10, 2);

			// launch import command
			$import_args = array_intersect_key($assoc_args, array('authors' => '', 'skip' => '', 'url' => '')); // only arguments allowed for wp import command
			WP_CLI::run_command(array_merge(array('import'), $args), $import_args);
			WP_CLI::success('ok');
		}

		private function orderTerms($assoc_args)
		{
			// tassonomia
			if(!empty($assoc_args['extra-terms-taxonomy'])) {
				$this->term_taxonomy = $assoc_args['extra-terms-taxonomy'];

				if(!taxonomy_exists($this->term_taxonomy)) {
					WP_CLI::error('Unexisting taxonomy', true);
				}
			}

			WP_CLI::line('Assumed ' . $this->term_taxonomy . ' how terms taxonomy to add');

			// terms
			if(!empty($assoc_args['extra-terms'])) {
				$this->terms = explode(',', $assoc_args['extra-terms']);
			}
		}

		private function orderPostMetas($assoc_args)
		{
			if(empty($assoc_args['extra-post-meta-keys']) || empty($assoc_args['extra-post-meta-values'])) {
				return;
			}

			$keys = explode(',', $assoc_args['extra-post-meta-keys']);
			$values = explode(',', $assoc_args['extra-post-meta-values']);

			foreach($keys as $i => $key) {

				if(empty($values[$i])) {
					return;
				}

				$this->post_metas[$key] = $values[$i];
			}
		}

		public function associateExtraData($post_id, $post)
		{
			if(wp_is_post_revision($post)) {
				return;
			}

			// terms
			if(!empty($this->terms)) {
				WP_CLI::line('--- Setting extra post terms to ' . $post->post_title);
				wp_set_post_terms($post_id, $this->terms, $this->term_taxonomy, true);
			}

			// post metas
			if(!empty($this->post_metas)) {
				WP_CLI::line('--- Setting extra post metas to '  . $post->post_title);
				foreach($this->post_metas as $key => $value) {
					add_post_meta($post_id, $key, $value);
				}
			}
		}
	}

	// command definition
	WP_CLI::add_command('import-plus', 'Import_Plus');
});

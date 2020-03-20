<?php

namespace Elgg\Blog;

use Elgg\Database\Seeds\Seed;

/**
 * Add blog seed
 *
 * @internal
 */
class Seeder extends Seed {

	private $status = [
		'draft',
		'published',
	];

	/**
	 * {@inheritdoc}
	 */
	public function seed() {
		$created = 0;

		$count_blogs = function () use (&$created) {
			if ($this->create) {
				return $created;
			};

			return elgg_count_entities([
				'types' => 'object',
				'subtypes' => 'blog',
				'metadata_names' => '__faker',
			]);
		};

		$this->advance($count_blogs());

		$attributes = [
			'subtype' => 'blog',
		];

		while ($count_blogs() < $this->limit) {
			$metadata = [
				'status' => $this->getRandomStatus(),
				'comments_on' => $this->faker()->boolean() ? 'On' : 'Off',
				'excerpt' => $this->faker()->sentence(),
			];

			$blog = $this->createObject($attributes, $metadata);
			if (!$blog) {
				continue;
			}

			$created++;

			$this->createComments($blog);
			$this->createLikes($blog);

			if ($blog->status === 'draft') {
				$blog->future_access = $blog->access_id;
				$blog->access_id = ACCESS_PRIVATE;
			}

			if ($blog->status === 'published') {
				elgg_create_river_item([
					'view' => 'river/object/blog/create',
					'action_type' => 'create',
					'subject_guid' => $blog->owner_guid,
					'object_guid' => $blog->guid,
					'target_guid' => $blog->container_guid,
				]);

				elgg_trigger_event('publish', 'object', $blog);
			}

			if ($this->faker()->boolean()) {
				$blog->annotate('blog_auto_save', $this->faker()->text(500), ACCESS_PRIVATE, $blog->owner_guid);
			}

			if ($this->faker()->boolean()) {
				$blog->annotate('blog_revision', $blog->description, ACCESS_PRIVATE, $blog->owner_guid);
				$blog->description = $this->faker()->text(500);
			}

			$blog->save();

			$this->advance();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function unseed() {

		$blogs = elgg_get_entities([
			'types' => 'object',
			'subtypes' => 'blog',
			'metadata_names' => '__faker',
			'limit' => 0,
			'batch' => true,
		]);

		/* @var $blogs \ElggBatch */

		$blogs->setIncrementOffset(false);

		foreach ($blogs as $blog) {
			if ($blog->delete()) {
				$this->log("Deleted blog $blog->guid");
			} else {
				$this->log("Failed to delete blog $blog->guid");
			}

			$this->advance();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getType() : string {
		return 'blog';
	}

	/**
	 * Returns random blog status
	 * @return string
	 */
	public function getRandomStatus() {
		$key = array_rand($this->status, 1);

		return $this->status[$key];
	}
}

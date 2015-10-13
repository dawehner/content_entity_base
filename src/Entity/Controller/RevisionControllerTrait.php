<?php

/**
 * @file
 * Contains
 *   \Drupal\content_entity_base\Entity\Controller\RevisionControllerTrait.
 */

namespace Drupal\content_entity_base\Entity\Controller;

use Drupal\content_entity_base\Entity\Revision\TimestampedRevisionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a trait for common revision UI functionality.
 */
trait RevisionControllerTrait {

  /**
   * @return \Drupal\Core\Entity\EntityManagerInterface
   */
  public abstract function entityManager();

  /**
   * @return \Drupal\Core\Render\RendererInterface
   */
  public abstract function renderer();

  /**
   * @return \Drupal\Core\Language\LanguageManagerInterface
   */
  public abstract function languageManager();

  /**
   * Displays an entity revision.
   *
   * @param int $revision_id
   *   The entity revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function showRevision($revision_id) {
    $entity = $this->entityManager()
      ->getStorage($this->getRevisionEntityTypeId())
      ->loadRevision($revision_id);
    $view_controller = $this->getEntityViewBuilder($this->entityManager(), $this->renderer());
    $page = $view_controller->view($entity);
    unset($page[$this->getRevisionEntityTypeId() . 's'][$entity->id()]['#cache']);
    return $page;
  }

  /**
   * Page title callback for an entity revision.
   *
   * @param int $revision_id
   *   The entity revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($revision_id) {
    $entity = $this->entityManager()
      ->getStorage($this->getRevisionEntityTypeId())
      ->loadRevision($revision_id);
    if ($entity instanceof TimestampedRevisionInterface) {
      return $this->t('Revision of %title from %date', array(
        '%title' => $entity->label(),
        '%date' => \Drupal::service('date.formatter')
          ->format($entity->getRevisionCreationTime()),
      ));
    }
    else {
      return $this->t('Revision of %title', array(
        '%title' => $entity->label(),
      ));
    }
  }

  /**
   * Determines if the user has permission to revert revisions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check revert access for.
   *
   * @return bool
   *   TRUE if the user has revert access.
   */
  abstract protected function hasRevertRevisionPermission(EntityInterface $entity);

  /**
   * Determines if the user has permission to delete revisions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check delete revision access for.
   *
   * @return bool
   *   TRUE if the user has delete revision access.
   */
  abstract protected function hasDeleteRevisionPermission(EntityInterface $entity);

  /**
   * Builds a link to revert an entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to build a revert revision link for.
   * @param int $revision_id
   *   The revision ID of the revert link.
   *
   * @return array
   *   A link render array.
   */
  abstract protected function buildRevertRevisionLink(EntityInterface $entity, $revision_id);

  /**
   * Builds a link to delete an entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to build a delete revision link for.
   * @param int $revision_id
   *   The revision ID of the delete link.
   *
   * @return array
   *   A link render array.
   */
  abstract protected function buildDeleteRevisionLink(EntityInterface $entity, $revision_id);

  /**
   * Returns a string providing details of the revision.
   *
   * E.g. Node describes its revisions using {date} by {username}. For the
   *   non-current revision, it also provides a link to view that revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $revision
   *   Returns a string to provide the details of the revision.
   * @param bool $is_current
   *   TRUE if the revision is the current revision.
   *
   * @return string
   *   Revision description.
   */
  abstract protected function getRevisionDescription(EntityInterface $revision, $is_current = FALSE);

  /**
   * Returns a string providing the title of the revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $revision
   *   Returns a string to provide the title of the revision.
   *
   * @return string
   *   Revision title.
   */
  abstract protected function getRevisionTitle(EntityInterface $revision);


  /**
   * Gets the entity type ID for this revision controller.
   *
   * @return string
   *   Entity Type ID for this revision controller.
   */
  abstract protected function getRevisionEntityTypeId();

  /**
   * Gets the entity's view controller.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   Entity manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer service.
   *
   * @return \Drupal\Core\Entity\EntityViewBuilderInterface
   *   A new entity view builder.
   */
  abstract protected function getEntityViewBuilder(EntityManagerInterface $entity_manager, RendererInterface $renderer);

  /**
   * Generates an overview table of older revisions of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   An entity object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(ContentEntityInterface $entity) {
    $langcode = $this->languageManager()
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();
    $entity_storage = $this->entityManager()
      ->getStorage($this->getRevisionEntityTypeId());

    $build['#title'] = $this->getRevisionTitle($entity);
    $header = array($this->t('Revision'), $this->t('Operations'));

    $rows = [];

    $vids = $entity_storage->revisionIds($entity);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      $row = [];
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $entity_storage->loadRevision($vid);
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)
          ->isRevisionTranslationAffected()
      ) {
        if ($latest_revision) {
          $row[] = $this->getRevisionDescription($revision, TRUE);
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $row[] = $this->getRevisionDescription($revision, FALSE);
          $links = $this->getOperationLinks($entity, $vid);

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }
      }

      $rows[] = $row;
    }

    $build[$this->getRevisionEntityTypeId() . '_revisions_table'] = array(
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    );

    return $build;
  }

  /**
   * Get the links of the operations for an entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to build the revision links for.
   * @param int $revision_id
   *   The revision ID of the delete link.
   *
   * @return array
   *   The operation links.
   */
  protected function getOperationLinks(EntityInterface $entity, $revision_id) {
    $links = [];
    $revert_permission = $this->hasRevertRevisionPermission($entity);
    $delete_permission = $this->hasDeleteRevisionPermission($entity);
    if ($revert_permission) {
      $links['revert'] = $this->buildRevertRevisionLink($entity, $revision_id);
    }

    if ($delete_permission) {
      $links['delete'] = $this->buildDeleteRevisionLink($entity, $revision_id);
    }
    return $links;
  }

}

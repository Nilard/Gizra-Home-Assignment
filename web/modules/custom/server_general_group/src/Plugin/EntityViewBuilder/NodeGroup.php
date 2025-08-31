<?php

namespace Drupal\server_general_group\Plugin\EntityViewBuilder;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\og\OgAccessInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\og\MembershipManager;
use Drupal\server_general\EntityViewBuilder\NodeViewBuilderAbstract;
use Drupal\server_general\ThemeTrait\TitleAndLabelsThemeTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "Node Group" plugin.
 *
 * @EntityViewBuilder(
 *   id = "node.group",
 *   label = @Translation("Node - Group"),
 *   description = "Node view builder for Group bundle."
 * )
 */
class NodeGroup extends NodeViewBuilderAbstract {

  use TitleAndLabelsThemeTrait;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected OgAccessInterface $ogAccess;

  /**
   * The OG membership manager.
   *
   * @var \Drupal\og\MembershipManager
   */
  protected MembershipManager $ogMembershipManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->ogAccess = $container->get('og.access');
    $plugin->ogMembershipManager = $container->get('og.membership_manager');

    return $plugin;
  }

  /**
   * Build full view mode.
   *
   * @param array $build
   *   The existing build.
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return array
   *   Render array.
   */
  public function buildFull(array $build, NodeInterface $entity): array {
    $elements = [];

    // Show the page title.
    $element = $this->buildPageTitle($entity->label());
    $elements[] = $this->wrapContainerWide($element);

    // Add subscription UI for authenticated users.
    if ($this->currentUser->isAuthenticated()) {
      $subscription_element = $this->buildSubscriptionUI($entity);
      if ($subscription_element) {
        $elements[] = $this->wrapContainerWide($subscription_element);
      }
    }

    // Wrap all elements with proper spacing.
    $elements = $this->wrapContainerVerticalSpacingBig($elements);
    // Add bottom padding for proper spacing with footer.
    $build[] = $this->wrapContainerBottomPadding($elements);

    return $build;
  }

  /**
   * Build subscription UI for the group.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group entity.
   *
   * @return array|null
   *   Render array for subscription UI or NULL if user cannot subscribe.
   */
  protected function buildSubscriptionUI(NodeInterface $entity): ?array {
    // Check if this is actually an OG group.
    if (!$entity->hasField('og_group')) {
      return NULL;
    }

    // Check if user can subscribe to this group.
    if (!$this->ogAccess->userAccess($entity, 'subscribe', $this->currentUser)) {
      return NULL;
    }

    // Check if user is already a member.
    $membership = $this->ogMembershipManager->getMembership($entity, $this->currentUser->id());

    if ($membership && $membership->getState() === OgMembershipInterface::STATE_ACTIVE) {
      // User is already a member - show membership info.
      return $this->buildMembershipInfo($entity, $membership);
    }

    // Show subscription UI.
    return $this->buildSubscribeForm($entity);
  }

  /**
   * Build subscribe form.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group entity.
   *
   * @return array
   *   Render array for subscription form.
   */
  protected function buildSubscribeForm(NodeInterface $entity): array {
    // Get user's display name.
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $user_name = $user ? $user->getDisplayName() : $this->currentUser->getDisplayName();

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['group-subscription-ui'],
      ],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Hi @name, click here if you would like to subscribe to this group called @group_label.', [
          '@name' => $user_name,
          '@group_label' => $entity->label(),
        ]),
      ],
      'subscribe_link' => [
        '#type' => 'link',
        '#title' => $this->t('Subscribe to Group'),
        '#url' => Url::fromRoute('og.subscribe', [
          'entity_type_id' => $entity->getEntityTypeId(),
          'group' => $entity->id(),
          'og_membership_type' => 'default',
        ]),
      ],
    ];
  }

  /**
   * Build membership info for existing members.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The group entity.
   * @param \Drupal\og\OgMembershipInterface $membership
   *   The user's membership.
   *
   * @return array
   *   Render array for membership info.
   */
  protected function buildMembershipInfo(NodeInterface $entity, OgMembershipInterface $membership): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['group-membership-info'],
      ],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('You are already a member of this group.'),
      ],
      'unsubscribe_link' => [
        '#type' => 'link',
        '#title' => $this->t('Leave Group'),
        '#url' => Url::fromRoute('og.unsubscribe', [
          'entity_type_id' => $entity->getEntityTypeId(),
          'group' => $entity->id(),
        ]),
      ],
    ];
  }

}

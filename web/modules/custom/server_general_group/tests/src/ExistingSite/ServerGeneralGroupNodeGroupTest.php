<?php

namespace Drupal\Tests\server_general_group\ExistingSite;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\Tests\server_general\ExistingSite\ServerGeneralTestBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test group subscription functionality.
 *
 * @group server_general_group
 */
class ServerGeneralGroupNodeGroupTest extends ServerGeneralTestBase {

  /**
   * Test group subscription UI for authenticated users.
   */
  public function testGroupSubscription() {
    // Create a test user who will SUBSCRIBE to the group.
    $user = $this->createUser();

    // Create ANOTHER user who will CREATE the group.
    $group_creator = $this->createUser();

    // Create a group FROM group_creator.
    $group = $this->createNode([
      'title' => 'Test Group for Subscription',
      'type' => 'group',
      'uid' => $group_creator->id(),
      'moderation_state' => 'published',
      'og_group' => [
        'value' => 1,
      ],
    ]);

    // Login as user who will subscribe (NOT the group creator).
    $this->drupalLogin($user);
    $this->drupalGet($group->toUrl());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    // Check that subscription UI is displayed.
    $this->assertSession()->pageTextContains('Hi ' . $user->getDisplayName());
    $this->assertSession()->pageTextContains('Test Group for Subscription');
    $this->assertSession()->linkExists('Subscribe to Group');

    // Check that the subscription message is personalized.
    $this->assertSession()->pageTextContains('click here if you would like to subscribe to this group called Test Group for Subscription');
  }

  /**
   * Test group subscription UI for anonymous users.
   */
  public function testGroupSubscriptionAnonymous() {
    // Create a user who will create the group.
    $group_creator = $this->createUser();

    // Create a group FROM group_creator.
    $group = $this->createNode([
      'title' => 'Test Group for Anonymous',
      'type' => 'group',
      'uid' => $group_creator->id(),
      'moderation_state' => 'published',
      'og_group' => [
        'value' => 1,
      ],
    ]);

    // Visit the group page as anonymous user.
    $this->drupalGet($group->toUrl());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    // Check that subscription UI is NOT displayed for anonymous users.
    $this->assertSession()->linkNotExists('Subscribe to Group');
    $this->assertSession()->pageTextNotContains('Subscribe to Group');
  }

  /**
   * Test group subscription UI for existing members.
   */
  public function testGroupSubscriptionExistingMember() {
    // Create a test user who will SUBSCRIBE to the group.
    $user = $this->createUser();

    // Create ANOTHER user who will CREATE the group.
    $group_creator = $this->createUser();

    // Create a group FROM group_creator.
    $group = $this->createNode([
      'title' => 'Test Group for Existing Member',
      'type' => 'group',
      'uid' => $group_creator->id(),
      'moderation_state' => 'published',
      'og_group' => [
        'value' => 1,
      ],
    ]);

    // Login as user who will subscribe.
    $this->drupalLogin($user);

    // Subscribe the user to the group.
    $this->subscribeUserToGroup($user, $group);

    // Visit the group page.
    $this->drupalGet($group->toUrl());
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    // Check that membership info is displayed instead of subscription UI.
    $this->assertSession()->pageTextContains('You are already a member of this group');
    $this->assertSession()->linkExists('Leave Group');
    $this->assertSession()->linkNotExists('Subscribe to Group');
  }

  /**
   * Helper method to subscribe user to group.
   */
  private function subscribeUserToGroup(User $user, Node $group) {
    // Check if membership already exists to avoid duplicates.
    if (\Drupal::hasService('og.membership_manager')) {
      $membership_manager = \Drupal::service('og.membership_manager');
      $existing_membership = $membership_manager->getMembership($group, $user->id());

      if ($existing_membership) {
        // Update existing membership to active state.
        $existing_membership->setState('active');
        $existing_membership->save();
        return;
      }

      // Create new membership only if it doesn't exist.
      $membership = $membership_manager->createMembership($group, $user, 'default');
      $membership->setState('active');
      $membership->save();
    }
  }

}

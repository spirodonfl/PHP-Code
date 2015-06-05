<?php

/**
 * @file
 * Defines Drupal\random_api\API\ImportController.
 */

namespace Drupal\random_api\API;

use Drupal\random_api\API\RestApi;
use Drupal\random_api\User\User;
use Drupal\random_api\Content\Category;

class ImportController {

  /**
   * Implements hook_menu().
   */
  public static function handleHookMenu() {
    $items = array();

    $items[RestApi::API_V1_PATH_PREFIX . '/import-new-user'] = array(
      'access arguments' => array('access content'),
      'page callback' => 'Drupal\random_api\Pages\PageController::renderPage',
      'page arguments' => array('Drupal\random_api\API\ImportController', 'importNewUser'),
      'type' => MENU_CALLBACK,
    );

    $items[RestApi::API_V1_PATH_PREFIX . '/import-category'] = array(
      'access arguments' => array('access content'),
      'page callback' => 'Drupal\random_api\Pages\PageController::renderPage',
      'page arguments' => array('Drupal\random_api\API\ImportController', 'importCategory', 3),
      'type' => MENU_CALLBACK,
    );

    return $items;
  }

  /**
   * Listens for either POST or PATCH for either a new Category or an Updated category respectively
   *
   * @param int $category_uuid
   *   Optionally the category UUID to PATCH (must be PATCH)
   *
   * @return string
   *   Just a literal string with a response text
   */
  public static function importCategory($category_uuid) {
    if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && !empty($category_uuid)) {
      $data = RestApi::requestData();

      if (Category::validateImportData($data)) {
        $data = Category::cleanUpData($data);

        $entities = entity_uuid_load('node', array($category_uuid));
        if (is_array($entities) && count($entities) > 0) {
          reset($entities);
          $entity = $entities[key($entities)];
          $category = Category::load($entity->nid);
          foreach ($data as $field_name => $field_value) {
            if (!empty($field_value)) {
              $category->updateFieldValue($field_name, $field_value, TRUE);
            }
          }
          return RestApi::responseSuccess(array('status' => 'Category updated'));
        }

        return RestApi::responseFail(array('Did not update category'), RestApi::STATUS_CODE_BAD_REQUEST, 'Did not update category');
      }
      else {
        return RestApi::responseFail(array('Validation did not pass'), RestApi::STATUS_CODE_BAD_REQUEST, 'Bad validation');
      }
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $data = RestApi::requestData();

      if (Category::validateImportData($data)) {
        $data = Category::cleanUpData($data);
        if ($category = Category::createNewCategoryObject($data)) {
          $category->save();
          return RestApi::responseSuccess(array('status' => 'Category created'));
        }
      }
      else {
        return RestApi::responseFail(array('Validation did not pass'), RestApi::STATUS_CODE_BAD_REQUEST, 'Bad validation');
      }
    }
  }

  /**
   * This is the function to post user data to test with posting application/json
   * {"username" : "dave", "email" : "email@email.com","password" : "mypass"}
   *
   * @return string
   *   Just a literal string for the response
   */
  public static function importNewUser() {
    $user_register = variable_get('user_register');
    if ($user_register == 0) {
      return RestApi::responseFail(array('You can not create an account'), RestApi::STATUS_CODE_NO_CONTENT, 'site account creation disabled');
    }

    $post_vars = RestApi::requestData();
    if (isset($post_vars) && $post_vars <> '') {
      $error_message = '';
      $error = false;
      
      if (isset($post_vars->username) && $post_vars->username <> '' && isset($post_vars->email) && $post_vars->email <> '' && isset($post_vars->password) && $post_vars->password <> '') {
        // check if there is any users with that name or email 
        $mail = check_plain($post_vars->email);
        $user_name = check_plain($post_vars->username);
        $password = check_plain($post_vars->password);

        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
          $error = true;
          $error_message = t('Invalid email format');
          return RestApi::responseFail(array($error_message), RestApi::STATUS_CODE_NO_CONTENT, $error_message);
        }

        $sql = 'SELECT * FROM {users} WHERE mail = :mail';
        $result = db_query($sql, array(':mail' => $mail));
        
        if ($result->rowCount() <> 0 ) {
          $error = true;
          $error_message = t('Sorry the email you entered is in use.');
          return RestApi::responseFail(array($error_message), RestApi::STATUS_CODE_NO_CONTENT, $error_message);
        }
        
        $sql = 'SELECT * FROM {users} WHERE name = :name';
        $result = db_query($sql, array(':name' => $user_name));
        
        if ($result->rowCount() <> 0 ) {
          $error = true;
          $error_message = t('Sorry the user name you entered is in use.');
          return RestApi::responseFail(array($error_message), RestApi::STATUS_CODE_NO_CONTENT, $error_message);
        }

        $user_roles = array( DRUPAL_AUTHENTICATED_RID => 'authenticated user' );
        if (isset($post_vars->role)) {
          $roles = user_roles();
          foreach ($roles as $rid => $name) {
            if ($post_vars->role === $name) {
              $user_roles[$rid] = $name;
            }
          }
        }
        
        if ($error == false) {
          $new_user = array(
           'name' => $user_name,
           'pass' => $password,
           'mail' => $mail,
           'status' => 1,
           'init' => $mail,
           'roles' => $user_roles
          );
          user_save(null, $new_user);       
          return RestApi::responseSuccess(array('status' => 'User Created'));
        }
      }
      else {
        $error_message = 'Please provide all field values';
        return RestApi::responseFail(array($error_message), RestApi::STATUS_CODE_NO_CONTENT, 'No Content Found.');
      }
    }
  }
}
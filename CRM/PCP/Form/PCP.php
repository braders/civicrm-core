<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

/**
 * Administer Personal Campaign Pages - Search form
 */
class CRM_PCP_Form_PCP extends CRM_Core_Form {

  public $_context;

  /**
   * The ID of the personal campaign page being acted upon
   *
   * @var int
   * @internal
   */
  public $_id;

  /**
   * Set variables up before form is built.
   *
   * @throws \CRM_Core_Exception
   */
  public function preProcess() {
    if ($this->_action & CRM_Core_Action::DELETE) {
      //check permission for action.
      if (!CRM_Core_Permission::checkActionPermission('CiviEvent', $this->_action)) {
        CRM_Core_Error::statusBounce(ts('You do not have permission to access this page.'));
      }

      $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);
      $this->_title = CRM_Core_DAO::getFieldValue('CRM_PCP_DAO_PCP', $this->_id, 'title');
      $this->assign('title', $this->_title);
      parent::preProcess();
    }

    if (!$this->_action) {
      $this->_action = $_GET['action'] ?? NULL;
      $this->_id = $_GET['id'] ?? NULL;
    }
    else {
      $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);
    }

    //give the context.
    if (!isset($this->_context)) {
      $this->_context = CRM_Utils_Request::retrieve('context', 'Alphanumeric', $this);
    }

    $this->assign('context', $this->_context);

    $session = CRM_Core_Session::singleton();
    $context = $session->popUserContext();
    $userID = $session->get('userID');

    //do not allow destructive actions without permissions
    $permission = FALSE;
    if (CRM_Core_Permission::check('administer CiviCRM') ||
      ($userID && (CRM_Core_DAO::getFieldValue('CRM_PCP_DAO_PCP',
            $this->_id,
            'contact_id'
          ) == $userID))
    ) {
      $permission = TRUE;
    }
    if ($permission && $this->_id) {

      $this->_title = CRM_Core_DAO::getFieldValue('CRM_PCP_DAO_PCP', $this->_id, 'title');
      switch ($this->_action) {
        case CRM_Core_Action::DELETE:
        case 'delete':
          CRM_PCP_BAO_PCP::deleteRecord(['id' => $this->_id]);
          CRM_Core_Session::setStatus(ts("The Campaign Page '%1' has been deleted.", [1 => $this->_title]), ts('Page Deleted'), 'success');
          break;

        case CRM_Core_Action::DISABLE:
        case 'disable':
          CRM_PCP_BAO_PCP::setDisable($this->_id, '0');
          CRM_Core_Session::setStatus(ts("The Campaign Page '%1' has been disabled.", [1 => $this->_title]), ts('Page Disabled'), 'success');
          break;

        case CRM_Core_Action::ENABLE:
        case 'enable':
          CRM_PCP_BAO_PCP::setDisable($this->_id, '1');
          CRM_Core_Session::setStatus(ts("The Campaign Page '%1' has been enabled.", [1 => $this->_title]), ts('Page Enabled'), 'success');
          break;
      }

      if ($context) {
        CRM_Utils_System::redirect($context);
      }
    }
  }

  /**
   * Set default values for the form. Note that in edit/view mode
   * the default values are retrieved from the database
   *
   * @return array
   *   array of default values
   */
  public function setDefaultValues() {
    $defaults = [];

    $pageType = CRM_Utils_Request::retrieve('page_type', 'String', $this);
    $defaults['page_type'] = !empty($pageType) ? $pageType : '';

    return $defaults;
  }

  /**
   * Build the form object.
   */
  public function buildQuickForm() {
    if ($this->_action & CRM_Core_Action::DELETE) {
      $this->addButtons([
          [
            'type' => 'next',
            'name' => ts('Delete Campaign'),
            'isDefault' => TRUE,
          ],
          [
            'type' => 'cancel',
            'name' => ts('Cancel'),
          ],
      ]);
    }
    else {

      $status = ['' => ts('- select -')] + CRM_Core_OptionGroup::values("pcp_status");
      $types = [
        '' => ts('- select -'),
        'contribute' => ts('Contribution'),
        'event' => ts('Event'),
      ];
      $contribPages = ['' => ts('- select -')] + CRM_Contribute_PseudoConstant::contributionPage();
      $eventPages = ['' => ts('- select -')] + CRM_Event_PseudoConstant::event(NULL, FALSE, "is_template = 0");

      $this->addElement('select', 'status_id', ts('Status'), $status);
      $this->addElement('select', 'page_type', ts('Source Type'), $types);
      $this->addElement('select', 'page_id', ts('Contribution Page'), $contribPages);
      $this->addElement('select', 'event_id', ts('Event Page'), $eventPages);
      $this->addButtons([
          [
            'type' => 'refresh',
            'name' => ts('Search'),
            'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
            'isDefault' => TRUE,
          ],
      ]);
      parent::buildQuickForm();
    }
  }

  /**
   * Global validation rules for the form.
   *
   * @param array $fields
   *   Posted values of the form.
   * @param array $files
   * @param CRM_Core_Form $form
   *
   * @return array|NULL
   *   list of errors to be posted back to the form
   */
  public static function formRule($fields, $files, $form) {
    return NULL;
  }

  /**
   * Process the form.
   */
  public function postProcess() {
    if ($this->_action & CRM_Core_Action::DELETE) {
      CRM_PCP_BAO_PCP::deleteRecord(['id' => $this->_id]);
      CRM_Core_Session::setStatus(ts("The Campaign Page '%1' has been deleted.", [1 => $this->_title]), ts('Page Deleted'), 'success');
    }
    else {
      $params = $this->controller->exportValues($this->_name);
      $parent = $this->controller->getParent();

      if (!empty($params)) {
        $fields = ['status_id', 'page_id'];
        foreach ($fields as $field) {
          if (isset($params[$field]) &&
            !CRM_Utils_System::isNull($params[$field])
          ) {
            $parent->set($field, $params[$field]);
          }
          else {
            $parent->set($field, NULL);
          }
        }
      }
    }
  }

}

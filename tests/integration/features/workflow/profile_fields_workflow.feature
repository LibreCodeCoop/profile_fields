Feature: profile field workflows
  Background:
    Given user "workflow_subject" exists

  Scenario: matching field updates notify configured admin targets through workflow and notifications OCS
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | workflow_notify_department |
      | label             | Workflow Notify Department |
      | type              | text                       |
      | adminOnly         | false                      |
      | userEditable      | true                       |
      | userVisible       | true                       |
      | initialVisibility | users                      |
      | sortOrder         | 10                         |
      | active            | true                       |
    Then the response should have a status code 201
    And fetch field "(WORKFLOW_NOTIFY_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/definitions/<WORKFLOW_NOTIFY_FIELD_ID>"
      | label             | Workflow Notify Department <WORKFLOW_NOTIFY_FIELD_ID> |
      | type              | text                                                  |
      | adminOnly         | false                                                 |
      | userEditable      | true                                                  |
      | userVisible       | true                                                  |
      | initialVisibility | users                                                 |
      | sortOrder         | 10                                                    |
      | active            | true                                                  |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/workflowengine/api/v1/workflows/global"
      """
      {
        "class": "OCA\\ProfileFields\\Workflow\\NotifyAdminsOrGroupsProfileFieldChangeOperation",
        "name": "workflow notify admins",
        "checks": [
          {
            "class": "OCA\\ProfileFields\\Workflow\\UserProfileFieldCheck",
            "operator": "is",
            "value": "{\"field_key\":\"workflow_notify_department\",\"value\":\"engineering\"}"
          }
        ],
        "operation": "{\"targets\":\"user:admin\"}",
        "entity": "OCA\\ProfileFields\\Workflow\\ProfileFieldValueEntity",
        "events": [
          "OCA\\ProfileFields\\Workflow\\Event\\ProfileFieldValueUpdatedEvent"
        ]
      }
      """
    Then the response should have a status code 200
    Given as user "workflow_subject"
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_NOTIFY_FIELD_ID>"
      | value             | finance |
      | currentVisibility | users   |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_NOTIFY_FIELD_ID>"
      | value             | engineering |
      | currentVisibility | users       |
    Then the response should have a status code 200
    Given as user "admin"
    When sending "get" to ocs "/apps/notifications/api/v2/notifications"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                                                                                                              | value                                                                  |
      | (jq).ocs.data[] \| select(.app == "profile_fields" and .user == "admin" and .object_type == "profile-field-admin-change" and .subject == "Profile field updated") \| .app      | profile_fields                                                         |
      | (jq).ocs.data[] \| select(.app == "profile_fields" and .user == "admin" and .object_type == "profile-field-admin-change" and .subject == "Profile field updated") \| .message  | workflow_subject changed workflow_subject's Workflow Notify Department <WORKFLOW_NOTIFY_FIELD_ID> profile field. |

  Scenario: matching field updates write workflow log entries
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | workflow_log_department |
      | label             | Workflow Log Department |
      | type              | text                    |
      | adminOnly         | false                   |
      | userEditable      | true                    |
      | userVisible       | true                    |
      | initialVisibility | users                   |
      | sortOrder         | 20                      |
      | active            | true                    |
    Then the response should have a status code 201
    And fetch field "(WORKFLOW_LOG_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/workflowengine/api/v1/workflows/global"
      """
      {
        "class": "OCA\\ProfileFields\\Workflow\\LogProfileFieldChangeOperation",
        "name": "workflow write log",
        "checks": [
          {
            "class": "OCA\\ProfileFields\\Workflow\\UserProfileFieldCheck",
            "operator": "is",
            "value": "{\"field_key\":\"workflow_log_department\",\"value\":\"operations\"}"
          }
        ],
        "operation": "",
        "entity": "OCA\\ProfileFields\\Workflow\\ProfileFieldValueEntity",
        "events": [
          "OCA\\ProfileFields\\Workflow\\Event\\ProfileFieldValueUpdatedEvent"
        ]
      }
      """
    Then the response should have a status code 200
    Given as user "workflow_subject"
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_LOG_FIELD_ID>"
      | value             | finance |
      | currentVisibility | users   |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_LOG_FIELD_ID>"
      | value             | operations |
      | currentVisibility | users      |
    Then the response should have a status code 200
    When run the bash command "grep -F 'Profile field workflow rule matched' <nextcloudRootDir>/data/nextcloud.log | tail -n 1" with result code 0
    Then the output of the last command should contain the following text:
      """
      Profile field workflow rule matched
      """
    And the output of the last command should contain the following text:
      """
      workflow_log_department
      """
    And the output of the last command should contain the following text:
      """
      workflow_subject
      """
    And the output of the last command should contain the following text:
      """
      operations
      """

Feature: profile field workflows
  Background:
    Given user "workflow_subject" exists
    And run the command "app:enable --force profile_fields" with result code 0
    And run the command "profile_fields:developer:reset --all" with result code 0

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

  Scenario: matching field updates send workflow webhook payloads
    Given as user "admin"
    And the mock web server "webhook-capture" is started
    And save the mock web server "webhook-capture" root URL as "WEBHOOK_CAPTURE_URL"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | workflow_webhook_department |
      | label             | Workflow Webhook Department |
      | type              | text                        |
      | adminOnly         | false                       |
      | userEditable      | true                        |
      | userVisible       | true                        |
      | initialVisibility | users                       |
      | sortOrder         | 30                          |
      | active            | true                        |
    Then the response should have a status code 201
    And fetch field "(WORKFLOW_WEBHOOK_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/workflowengine/api/v1/workflows/global"
      """
      {
        "class": "OCA\\ProfileFields\\Workflow\\SendWebhookProfileFieldChangeOperation",
        "name": "workflow send webhook",
        "checks": [
          {
            "class": "OCA\\ProfileFields\\Workflow\\UserProfileFieldCheck",
            "operator": "is",
            "value": "{\"field_key\":\"workflow_webhook_department\",\"value\":\"security\"}"
          }
        ],
        "operation": "{\"url\":\"<WEBHOOK_CAPTURE_URL>/profile-fields\",\"secret\":\"shared-secret\",\"timeout\":10,\"retries\":0,\"headers\":{\"X-Test-Suite\":\"profile_fields\"}}",
        "entity": "OCA\\ProfileFields\\Workflow\\ProfileFieldValueEntity",
        "events": [
          "OCA\\ProfileFields\\Workflow\\Event\\ProfileFieldValueUpdatedEvent"
        ]
      }
      """
    Then the response should have a status code 200
    Given as user "workflow_subject"
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_WEBHOOK_FIELD_ID>"
      | value             | finance |
      | currentVisibility | users   |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_WEBHOOK_FIELD_ID>"
      | value             | security |
      | currentVisibility | users    |
    Then the response should have a status code 200
    When read the last request from mock web server "webhook-capture"
    Then the output of the last command should contain the following text:
      """
      "METHOD": "POST"
      """
    And the output of the last command should contain the following text:
      """
      "REQUEST_URI": "/profile-fields"
      """
    And the output of the last command should contain the following text:
      """
      "X-Test-Suite": "profile_fields"
      """
    And the output of the last command should contain the following text:
      """
      "X-Profile-Fields-Signature": "sha256=
      """
    When read the last request body from mock web server "webhook-capture"
    And the output of the last command should contain the following text:
      """
      "name": "workflow send webhook"
      """
    And the output of the last command should contain the following text:
      """
      "key": "workflow_webhook_department"
      """
    And the output of the last command should contain the following text:
      """
      "previousValue": "finance"
      """
    And the output of the last command should contain the following text:
      """
      "currentValue": "security"
      """

  Scenario: matching field updates email the affected user
    Given as user "admin"
    And run the command "config:system:set mail_smtpmode --value smtp" with result code 0
    And run the command "config:system:set mail_smtphost --value mailpit" with result code 0
    And run the command "config:system:set mail_smtpport --value 1025 --type integer" with result code 0
    And run the command "config:system:set mail_smtpauth --value false --type boolean" with result code 0
    And run the command "config:system:set mail_smtpsecure --value \"\"" with result code 0
    And run the command "config:system:set mail_from_address --value profile-fields" with result code 0
    And run the command "config:system:set mail_domain --value example.test" with result code 0
    And set the email of user "workflow_subject" to "workflow_subject@example.test"
    And my inbox is empty
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | workflow_email_department |
      | label             | Workflow Email Department |
      | type              | text                      |
      | adminOnly         | false                     |
      | userEditable      | true                      |
      | userVisible       | true                      |
      | initialVisibility | users                     |
      | sortOrder         | 40                        |
      | active            | true                      |
    Then the response should have a status code 201
    And fetch field "(WORKFLOW_EMAIL_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/workflowengine/api/v1/workflows/global"
      """
      {
        "class": "OCA\\ProfileFields\\Workflow\\EmailUserProfileFieldChangeOperation",
        "name": "workflow email user",
        "checks": [
          {
            "class": "OCA\\ProfileFields\\Workflow\\UserProfileFieldCheck",
            "operator": "is",
            "value": "{\"field_key\":\"workflow_email_department\",\"value\":\"compliance\"}"
          }
        ],
        "operation": "{\"subjectTemplate\":\"Profile update: {{fieldLabel}}\",\"bodyTemplate\":\"Field {{fieldLabel}} changed from {{previousValue}} to {{currentValue}} by {{actorUid}}.\"}",
        "entity": "OCA\\ProfileFields\\Workflow\\ProfileFieldValueEntity",
        "events": [
          "OCA\\ProfileFields\\Workflow\\Event\\ProfileFieldValueUpdatedEvent"
        ]
      }
      """
    Then the response should have a status code 200
    Given as user "workflow_subject"
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_EMAIL_FIELD_ID>"
      | value             | finance |
      | currentVisibility | users   |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_EMAIL_FIELD_ID>"
      | value             | compliance |
      | currentVisibility | users      |
    Then the response should have a status code 200
    And there should be 1 email in my inbox
    When I open the latest email to "workflow_subject@example.test" with subject "Profile update: Workflow Email Department"
    Then I should see "Field Workflow Email Department changed from finance to compliance by workflow_subject." in the opened email

  Scenario: matching field updates create Talk conversations for admins and affected users
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | workflow_talk_department |
      | label             | Workflow Talk Department |
      | type              | text                     |
      | adminOnly         | false                    |
      | userEditable      | true                     |
      | userVisible       | true                     |
      | initialVisibility | users                    |
      | sortOrder         | 50                       |
      | active            | true                     |
    Then the response should have a status code 201
    And fetch field "(WORKFLOW_TALK_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/definitions/<WORKFLOW_TALK_FIELD_ID>"
      | label             | Workflow Talk Department <WORKFLOW_TALK_FIELD_ID> |
      | type              | text                                               |
      | adminOnly         | false                                              |
      | userEditable      | true                                               |
      | userVisible       | true                                               |
      | initialVisibility | users                                              |
      | sortOrder         | 50                                                 |
      | active            | true                                               |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/workflowengine/api/v1/workflows/global"
      """
      {
        "class": "OCA\\ProfileFields\\Workflow\\CreateTalkConversationProfileFieldChangeOperation",
        "name": "workflow create talk conversation",
        "checks": [
          {
            "class": "OCA\\ProfileFields\\Workflow\\UserProfileFieldCheck",
            "operator": "is",
            "value": "{\"field_key\":\"workflow_talk_department\",\"value\":\"support\"}"
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
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_TALK_FIELD_ID>"
      | value             | finance |
      | currentVisibility | users   |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<WORKFLOW_TALK_FIELD_ID>"
      | value             | support |
      | currentVisibility | users   |
    Then the response should have a status code 200
    Given as user "admin"
    When sending "get" to ocs "/apps/spreed/api/v4/room"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                                                             | value                                       |
      | (jq).ocs.data[] \| select(.displayName == "Profile field change: Workflow Talk Department <WORKFLOW_TALK_FIELD_ID> for workflow_subject") \| .displayName | Profile field change: Workflow Talk Department <WORKFLOW_TALK_FIELD_ID> for workflow_subject |
    Given as user "workflow_subject"
    When sending "get" to ocs "/apps/spreed/api/v4/room"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                                                             | value                                       |
      | (jq).ocs.data[] \| select(.displayName == "Profile field change: Workflow Talk Department <WORKFLOW_TALK_FIELD_ID> for workflow_subject") \| .displayName | Profile field change: Workflow Talk Department <WORKFLOW_TALK_FIELD_ID> for workflow_subject |

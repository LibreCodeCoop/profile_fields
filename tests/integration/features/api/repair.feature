Feature: orphaned profile field value repair
  Background:
    Given user "repairuser" exists
    And run the command "profile_fields:developer:reset --all" with result code 0

  Scenario: maintenance:repair removes values for deleted users without errors
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey       | repair_test_field |
      | label          | Repair test       |
      | type           | text              |
      | editPolicy     | users             |
      | exposurePolicy | private           |
      | sortOrder      | 10                |
      | active         | true              |
    Then the response should have a status code 201
    And fetch field "(REPAIR_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/repairuser/values/<REPAIR_FIELD_ID>"
      | value | orphaned |
    Then the response should have a status code 200
    And run the command "user:delete repairuser" with result code 0
    And run the command "maintenance:repair" with result code 0
    And the output of the last command should contain the following text:
      """
      Repair orphaned profile field values
      """

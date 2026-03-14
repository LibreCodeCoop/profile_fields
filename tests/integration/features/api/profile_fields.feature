Feature: profile fields API
  Background:
    Given user "profileuser" exists

  Scenario: unauthenticated users cannot list their own fields
    Given as user ""
    When sending "get" to ocs "/apps/profile_fields/api/v1/me/values"
    Then the response should have a status code 401
    And the response should be a JSON array with the following mandatory values
      | key                       | value                    |
      | (jq).ocs.meta.statuscode  | 997                      |
      | (jq).ocs.meta.message     | Current user is not logged in |

  Scenario: admins can manage field definitions
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | employee_code |
      | label             | Employee code |
      | type              | text          |
      | adminOnly         | false         |
      | userEditable      | true          |
      | userVisible       | true          |
      | initialVisibility | users         |
      | sortOrder         | 10            |
      | active            | true          |
    Then the response should have a status code 201
    And the response should be a JSON array with the following mandatory values
      | key                              | value         |
      | (jq).ocs.data.field_key          | employee_code |
      | (jq).ocs.data.label              | Employee code |
      | (jq).ocs.data.user_editable      | true          |
      | (jq).ocs.data.initial_visibility | users         |
    And fetch field "(FIELD_DEFINITION_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/definitions/<FIELD_DEFINITION_ID>"
      | label             | Employee ID |
      | type              | text        |
      | adminOnly         | true        |
      | userEditable      | false       |
      | userVisible       | true        |
      | initialVisibility | private     |
      | sortOrder         | 30          |
      | active            | true        |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                              | value                 |
      | (jq).ocs.data.id                 | <FIELD_DEFINITION_ID> |
      | (jq).ocs.data.label              | Employee ID           |
      | (jq).ocs.data.admin_only         | true                  |
      | (jq).ocs.data.user_editable      | false                 |
      | (jq).ocs.data.initial_visibility | private               |
    When sending "get" to ocs "/apps/profile_fields/api/v1/definitions"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value                 |
      | (jq).ocs.data[0].id                 | <FIELD_DEFINITION_ID> |
      | (jq).ocs.data[0].field_key          | employee_code         |
      | (jq).ocs.data[0].label              | Employee ID           |
      | (jq).ocs.data[0].admin_only         | true                  |
      | (jq).ocs.data[0].user_editable      | false                 |
      | (jq).ocs.data[0].initial_visibility | private               |
    When sending "delete" to ocs "/apps/profile_fields/api/v1/definitions/<FIELD_DEFINITION_ID>"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                     | value                 |
      | (jq).ocs.data.id        | <FIELD_DEFINITION_ID> |
      | (jq).ocs.data.field_key | employee_code         |
    When sending "get" to ocs "/apps/profile_fields/api/v1/definitions"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key           | value            |
      | (jq).ocs.data | (jq)length == 0 |

  Scenario: admins and users can manage stored values
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | nickname |
      | label             | Nickname |
      | type              | text     |
      | adminOnly         | false    |
      | userEditable      | true     |
      | userVisible       | true     |
      | initialVisibility | users    |
      | sortOrder         | 10       |
      | active            | true     |
    Then the response should have a status code 201
    And fetch field "(NICKNAME_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | employee_id |
      | label             | Employee ID |
      | type              | text        |
      | adminOnly         | true        |
      | userEditable      | false       |
      | userVisible       | true        |
      | initialVisibility | users       |
      | sortOrder         | 20          |
      | active            | true        |
    Then the response should have a status code 201
    And fetch field "(EMPLOYEE_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<EMPLOYEE_FIELD_ID>"
      | value             | EMP-001 |
      | currentVisibility | users   |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                               | value               |
      | (jq).ocs.data.field_definition_id | <EMPLOYEE_FIELD_ID> |
      | (jq).ocs.data.user_uid            | profileuser         |
      | (jq).ocs.data.value.value         | EMP-001             |
      | (jq).ocs.data.current_visibility  | users               |
    Given as user "profileuser"
    When sending "get" to ocs "/apps/profile_fields/api/v1/me/values"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                       | value       |
      | (jq).ocs.data[0].definition.field_key     | nickname    |
      | (jq).ocs.data[0].can_edit                 | true        |
      | (jq).ocs.data[0].value                    | null        |
      | (jq).ocs.data[1].definition.field_key     | employee_id |
      | (jq).ocs.data[1].can_edit                 | false       |
      | (jq).ocs.data[1].value.value.value        | EMP-001     |
      | (jq).ocs.data[1].value.current_visibility | users       |
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<NICKNAME_FIELD_ID>"
      | value             | Alpha |
      | currentVisibility | users |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                               | value               |
      | (jq).ocs.data.field_definition_id | <NICKNAME_FIELD_ID> |
      | (jq).ocs.data.user_uid            | profileuser         |
      | (jq).ocs.data.value.value         | Alpha               |
      | (jq).ocs.data.current_visibility  | users               |
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<EMPLOYEE_FIELD_ID>"
      | value | denied |
    Then the response should have a status code 403
    And the response should be a JSON array with the following mandatory values
      | key                   | value                               |
      | (jq).ocs.data.message | Field cannot be edited by the user |
    When sending "put" to ocs "/apps/profile_fields/api/v1/me/values/<NICKNAME_FIELD_ID>/visibility"
      | currentVisibility | public |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                               | value               |
      | (jq).ocs.data.field_definition_id | <NICKNAME_FIELD_ID> |
      | (jq).ocs.data.current_visibility  | public              |
    Given as user "admin"
    When sending "get" to ocs "/apps/profile_fields/api/v1/users/profileuser/values"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                                         | value            |
      | (jq).ocs.data                                                                               | (jq)length == 2 |
      | (jq).ocs.data[] \| select(.field_definition_id == <NICKNAME_FIELD_ID>) \| .value.value    | Alpha            |
      | (jq).ocs.data[] \| select(.field_definition_id == <NICKNAME_FIELD_ID>) \| .current_visibility | public       |
      | (jq).ocs.data[] \| select(.field_definition_id == <EMPLOYEE_FIELD_ID>) \| .value.value    | EMP-001          |

  Scenario: payroll ETL can resolve a cooperado by cpf and read the other payment fields
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | cpf |
      | label             | CPF |
      | type              | text |
      | adminOnly         | false |
      | userEditable      | false |
      | userVisible       | true |
      | initialVisibility | private |
      | sortOrder         | 10 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(CPF_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | health_plan_type |
      | label             | Health plan type |
      | type              | text |
      | adminOnly         | false |
      | userEditable      | false |
      | userVisible       | true |
      | initialVisibility | private |
      | sortOrder         | 20 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(HEALTH_PLAN_TYPE_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | health_plan_installment |
      | label             | Health plan installment |
      | type              | number |
      | adminOnly         | false |
      | userEditable      | false |
      | userVisible       | true |
      | initialVisibility | private |
      | sortOrder         | 30 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(HEALTH_PLAN_INSTALLMENT_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | social_capital_recurring |
      | label             | Social capital recurring |
      | type              | number |
      | adminOnly         | false |
      | userEditable      | false |
      | userVisible       | true |
      | initialVisibility | private |
      | sortOrder         | 40 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(SOCIAL_CAPITAL_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | corporate_mobile_plan |
      | label             | Corporate mobile plan |
      | type              | text |
      | adminOnly         | false |
      | userEditable      | false |
      | userVisible       | true |
      | initialVisibility | private |
      | sortOrder         | 50 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(CORPORATE_MOBILE_PLAN_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<CPF_FIELD_ID>"
      | value             | 12345678900 |
      | currentVisibility | private     |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<HEALTH_PLAN_TYPE_FIELD_ID>"
      | value             | coop-premium |
      | currentVisibility | private      |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<HEALTH_PLAN_INSTALLMENT_FIELD_ID>"
      | value             | 480.55 |
      | currentVisibility | private |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<SOCIAL_CAPITAL_FIELD_ID>"
      | value             | 150 |
      | currentVisibility | private |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<CORPORATE_MOBILE_PLAN_FIELD_ID>"
      | value             | enabled |
      | currentVisibility | private |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/profile_fields/api/v1/users/lookup"
      | fieldKey   | cpf |
      | fieldValue | 12345678900 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                                  | value         |
      | (jq).ocs.data.user_uid                                                              | profileuser   |
      | (jq).ocs.data.lookup_field_key                                                      | cpf           |
      | (jq).ocs.data.fields.cpf.value.value.value                                          | 12345678900   |
      | (jq).ocs.data.fields.health_plan_type.value.value.value                             | coop-premium  |
      | (jq).ocs.data.fields.health_plan_installment.value.value.value                      | 480.55        |
      | (jq).ocs.data.fields.social_capital_recurring.value.value.value                     | 150           |
      | (jq).ocs.data.fields.corporate_mobile_plan.value.value.value                        | enabled       |

  Scenario: payroll ETL gets a conflict when cpf is duplicated across users
    Given user "profileuser2" exists
    And as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | cpf |
      | label             | CPF |
      | type              | text |
      | adminOnly         | false |
      | userEditable      | false |
      | userVisible       | true |
      | initialVisibility | private |
      | sortOrder         | 10 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(DUPLICATE_CPF_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<DUPLICATE_CPF_FIELD_ID>"
      | value             | 99999999999 |
      | currentVisibility | private     |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser2/values/<DUPLICATE_CPF_FIELD_ID>"
      | value             | 99999999999 |
      | currentVisibility | private     |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/profile_fields/api/v1/users/lookup"
      | fieldKey   | cpf |
      | fieldValue | 99999999999 |
    Then the response should have a status code 409
    And the response should be a JSON array with the following mandatory values
      | key                   | value                                      |
      | (jq).ocs.data.message | Multiple users match the lookup field value |

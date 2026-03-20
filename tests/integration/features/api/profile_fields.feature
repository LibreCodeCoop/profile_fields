Feature: profile fields API
  Background:
    Given user "profileuser" exists
    And run the command "profile_fields:developer:reset --all" with result code 0

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
      | editPolicy        | users         |
      | exposurePolicy    | users         |
      | sortOrder         | 10            |
      | active            | true          |
    Then the response should have a status code 201
    And the response should be a JSON array with the following mandatory values
      | key                              | value         |
      | (jq).ocs.data.field_key          | employee_code |
      | (jq).ocs.data.label              | Employee code |
      | (jq).ocs.data.edit_policy        | users         |
      | (jq).ocs.data.exposure_policy    | users         |
    And fetch field "(FIELD_DEFINITION_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/definitions/<FIELD_DEFINITION_ID>"
      | label             | Employee ID |
      | type              | text        |
      | editPolicy        | admins      |
      | exposurePolicy    | private     |
      | sortOrder         | 30          |
      | active            | true        |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                              | value                 |
      | (jq).ocs.data.id                 | <FIELD_DEFINITION_ID> |
      | (jq).ocs.data.label              | Employee ID           |
      | (jq).ocs.data.edit_policy        | admins                |
      | (jq).ocs.data.exposure_policy    | private               |
    When sending "get" to ocs "/apps/profile_fields/api/v1/definitions"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value                 |
      | (jq).ocs.data[0].id                 | <FIELD_DEFINITION_ID> |
      | (jq).ocs.data[0].field_key          | employee_code         |
      | (jq).ocs.data[0].label              | Employee ID           |
      | (jq).ocs.data[0].edit_policy        | admins                |
      | (jq).ocs.data[0].exposure_policy    | private               |
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
      | editPolicy        | users    |
      | exposurePolicy    | users    |
      | sortOrder         | 10       |
      | active            | true     |
    Then the response should have a status code 201
    And fetch field "(NICKNAME_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | employee_id |
      | label             | Employee ID |
      | type              | text        |
      | editPolicy        | admins      |
      | exposurePolicy    | users       |
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
      | editPolicy        | admins |
      | exposurePolicy    | private |
      | sortOrder         | 10 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(CPF_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | health_plan_type |
      | label             | Health plan type |
      | type              | text |
      | editPolicy        | admins |
      | exposurePolicy    | private |
      | sortOrder         | 20 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(HEALTH_PLAN_TYPE_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | health_plan_installment |
      | label             | Health plan installment |
      | type              | number |
      | editPolicy        | admins |
      | exposurePolicy    | private |
      | sortOrder         | 30 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(HEALTH_PLAN_INSTALLMENT_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | social_capital_recurring |
      | label             | Social capital recurring |
      | type              | number |
      | editPolicy        | admins |
      | exposurePolicy    | private |
      | sortOrder         | 40 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(SOCIAL_CAPITAL_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | corporate_mobile_plan |
      | label             | Corporate mobile plan |
      | type              | text |
      | editPolicy        | admins |
      | exposurePolicy    | private |
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
      | editPolicy        | admins |
      | exposurePolicy    | private |
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

  Scenario: admins can search users by field value with pagination
    Given user "profileuser2" exists
    And user "profileuser3" exists
    And as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | region |
      | label             | Region |
      | type              | text |
      | editPolicy        | admins |
      | exposurePolicy    | private |
      | sortOrder         | 10 |
      | active            | true |
    Then the response should have a status code 201
    And fetch field "(REGION_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<REGION_FIELD_ID>"
      | value             | LATAM - South |
      | currentVisibility | private |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser2/values/<REGION_FIELD_ID>"
      | value             | LATAM - North |
      | currentVisibility | private |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser3/values/<REGION_FIELD_ID>"
      | value             | EMEA |
      | currentVisibility | private |
    Then the response should have a status code 200
    When sending "get" to ocs "/apps/profile_fields/api/v1/users/search?fieldKey=region&operator=contains&value=latam&limit=1&offset=1"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                                                                     | value           |
      | (jq).ocs.data.pagination.total                                                         | 2               |
      | (jq).ocs.data.pagination.limit                                                         | 1               |
      | (jq).ocs.data.pagination.offset                                                        | 1               |
      | (jq).ocs.data.items                                                                     | (jq)length == 1 |
      | (jq).ocs.data.items[0].user_uid                                                        | profileuser2    |
      | (jq).ocs.data.items[0].display_name                                                    | profileuser2-displayname |
      | (jq).ocs.data.items[0].fields.region.definition.field_key                              | region          |
      | (jq).ocs.data.items[0].fields.region.value.value.value                                 | LATAM - North   |
      | (jq).ocs.data.items[0].fields.region.value.current_visibility                          | private         |

  Scenario: admins get a bad request when search operator is not supported
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | region |
      | label             | Region |
      | type              | text |
      | editPolicy        | admins |
      | exposurePolicy    | private |
      | sortOrder         | 10 |
      | active            | true |
    Then the response should have a status code 201
    When sending "get" to ocs "/apps/profile_fields/api/v1/users/search?fieldKey=region&operator=startsWith&value=latam"
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                   | value                            |
      | (jq).ocs.data.message | The search operator is not supported. |

  Scenario: admins get not found when search field definition does not exist
    Given as user "admin"
    When sending "get" to ocs "/apps/profile_fields/api/v1/users/search?fieldKey=unknown_region&operator=eq&value=LATAM"
    Then the response should have a status code 404
    And the response should be a JSON array with the following mandatory values
      | key                   | value                           |
      | (jq).ocs.data.message | Search field definition not found |

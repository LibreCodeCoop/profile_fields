Feature: profile fields unified search
  Background:
    Given user "profileuser" exists
    And user "profileuser2" exists
    And user "profileuser3" exists
    And user "profileviewer" exists
    And user "directoryviewer" exists
    And run the command "profile_fields:developer:reset --all" with result code 0

  Scenario: admins can use unified search without an explicit cursor and continue with a numeric cursor
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | unified_region_admin |
      | label             | Unified Region Admin |
      | type              | text                 |
      | editPolicy        | admins               |
      | exposurePolicy    | private              |
      | sortOrder         | 10                   |
      | active            | true                 |
    Then the response should have a status code 201
    And fetch field "(UNIFIED_REGION_ADMIN_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<UNIFIED_REGION_ADMIN_FIELD_ID>"
      | value             | LATAM - South |
      | currentVisibility | private       |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser2/values/<UNIFIED_REGION_ADMIN_FIELD_ID>"
      | value             | LATAM - North |
      | currentVisibility | private       |
    Then the response should have a status code 200
    When sending "get" to ocs "/search/providers/profile_fields.directory/search?term=latam&limit=1&from=/settings/users"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value                              |
      | (jq).ocs.data.name                 | Profile directory                  |
      | (jq).ocs.data.isPaginated          | true                               |
      | (jq).ocs.data.cursor               | 1                                  |
      | (jq).ocs.data.entries              | (jq)length == 1                    |
      | (jq).ocs.data.entries[0].title     | profileuser-displayname            |
      | (jq).ocs.data.entries[0].subline   | Unified Region Admin: LATAM - South |
      | (jq).ocs.data.entries[0].icon      | icon-user                          |
      | (jq).ocs.data.entries[0].rounded   | true                               |
    When sending "get" to ocs "/search/providers/profile_fields.directory/search?term=latam&limit=1&cursor=1&from=/settings/users"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value                              |
      | (jq).ocs.data.name                 | Profile directory                  |
      | (jq).ocs.data.isPaginated          | false                              |
      | (jq).ocs.data.cursor               | null                               |
      | (jq).ocs.data.entries              | (jq)length == 1                    |
      | (jq).ocs.data.entries[0].title     | profileuser2-displayname           |
      | (jq).ocs.data.entries[0].subline   | Unified Region Admin: LATAM - North |

  Scenario: authenticated users can use unified search and it only returns exposed values
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | unified_region_visibility |
      | label             | Unified Region Visibility |
      | type              | text                      |
      | editPolicy        | admins                    |
      | exposurePolicy    | private                   |
      | sortOrder         | 10                        |
      | active            | true                      |
    Then the response should have a status code 201
    And fetch field "(UNIFIED_REGION_VISIBILITY_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<UNIFIED_REGION_VISIBILITY_FIELD_ID>"
      | value             | LATAM - Public |
      | currentVisibility | public         |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser2/values/<UNIFIED_REGION_VISIBILITY_FIELD_ID>"
      | value             | LATAM - Users |
      | currentVisibility | users         |
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser3/values/<UNIFIED_REGION_VISIBILITY_FIELD_ID>"
      | value             | LATAM - Private |
      | currentVisibility | private         |
    Then the response should have a status code 200
    Given as user "profileviewer"
    When sending "get" to ocs "/search/providers/profile_fields.directory/search?term=latam&from=/settings/users"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value                                   |
      | (jq).ocs.data.name                 | Profile directory                       |
      | (jq).ocs.data.isPaginated          | false                                   |
      | (jq).ocs.data.entries              | (jq)length == 2                         |
      | (jq).ocs.data.entries[0].title     | profileuser-displayname                 |
      | (jq).ocs.data.entries[0].subline   | Unified Region Visibility: LATAM - Public |
      | (jq).ocs.data.entries[1].title     | profileuser2-displayname                |
      | (jq).ocs.data.entries[1].subline   | Unified Region Visibility: LATAM - Users |

  Scenario: hidden fields and private values are only searchable by admins
    Given as user "admin"
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | unified_region_hidden     |
      | label             | Unified Region Hidden     |
      | type              | text                      |
      | editPolicy        | admins                    |
      | exposurePolicy    | hidden                    |
      | sortOrder         | 10                        |
      | active            | true                      |
    Then the response should have a status code 201
    And fetch field "(UNIFIED_REGION_HIDDEN_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileuser/values/<UNIFIED_REGION_HIDDEN_FIELD_ID>"
      | value             | LATAM - Hidden |
      | currentVisibility | public         |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/profile_fields/api/v1/definitions"
      | fieldKey          | unified_region_private    |
      | label             | Unified Region Private    |
      | type              | text                      |
      | editPolicy        | admins                    |
      | exposurePolicy    | private                   |
      | sortOrder         | 20                        |
      | active            | true                      |
    Then the response should have a status code 201
    And fetch field "(UNIFIED_REGION_PRIVATE_FIELD_ID)(jq).ocs.data.id" from previous JSON response
    When sending "put" to ocs "/apps/profile_fields/api/v1/users/profileviewer/values/<UNIFIED_REGION_PRIVATE_FIELD_ID>"
      | value             | LATAM - Private |
      | currentVisibility | private         |
    Then the response should have a status code 200
    Given as user "profileviewer"
    When sending "get" to ocs "/search/providers/profile_fields.directory/search?term=latam&from=/settings/users"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value               |
      | (jq).ocs.data.name                 | Profile directory   |
      | (jq).ocs.data.isPaginated          | false               |
      | (jq).ocs.data.entries              | (jq)length == 0     |
    Given as user "admin"
    When sending "get" to ocs "/search/providers/profile_fields.directory/search?term=latam&from=/settings/users"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                                 | value                                  |
      | (jq).ocs.data.name                 | Profile directory                      |
      | (jq).ocs.data.isPaginated          | false                                  |
      | (jq).ocs.data.entries              | (jq)length == 2                        |
      | (jq).ocs.data.entries[0].title     | profileuser-displayname                |
      | (jq).ocs.data.entries[0].subline   | Unified Region Hidden: LATAM - Hidden  |
      | (jq).ocs.data.entries[1].title     | profileviewer-displayname              |
      | (jq).ocs.data.entries[1].subline   | Unified Region Private: LATAM - Private |

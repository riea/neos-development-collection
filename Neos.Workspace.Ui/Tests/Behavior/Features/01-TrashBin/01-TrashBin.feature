Feature: Tests for the trash bin

  Background:
    Given using the following content dimensions:
      | Identifier | Values                         | Generalizations                         |
      | example    | general, source, special, peer | special->source->general, peer->general |
    And using the following node types:
    """yaml
    'Neos.ContentRepository:Root': {}
    'Neos.Neos:Sites':
      superTypes:
        'Neos.ContentRepository:Root': true
    'Neos.Neos:Document':
      label: ${node.properties.title}
      properties:
        title:
          type: string
        uriPathSegment:
          type: string
    'Neos.Neos:OtherDocument':
      superTypes:
        'Neos.Neos:Document': true
    'Neos.Neos:Site':
      superTypes:
        'Neos.Neos:Document': true
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And I am user identified by "initiating-user-identifier"

    When the command CreateRootWorkspace is executed with payload:
      | Key                | Value           |
      | workspaceName      | "live"          |
      | newContentStreamId | "cs-identifier" |
    And I am in workspace "live" and dimension space point {"example": "source"}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                    |
      | nodeAggregateId | "lady-eleonode-rootford" |
      | nodeTypeName    | "Neos.Neos:Sites"        |
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId        | parentNodeAggregateId  | nodeTypeName       | initialPropertyValues |
      | sir-david-nodenborough | lady-eleonode-rootford | Neos.Neos:Site     | {}                    |
      | nodingers-cat          | sir-david-nodenborough | Neos.Neos:Document | {"title": "Cat"}      |
      | nodingers-kitten       | nodingers-cat          | Neos.Neos:Document | {"title": "Kitten"}   |
    And the command CreateWorkspace is executed with payload:
      | Key                | Value              |
      | workspaceName      | "review-workspace" |
      | baseWorkspaceName  | "live"             |
      | newContentStreamId | "review-cs-id"     |
    And the command CreateWorkspace is executed with payload:
      | Key                | Value              |
      | workspaceName      | "user-workspace"   |
      | baseWorkspaceName  | "review-workspace" |
      | newContentStreamId | "user-cs-id"       |

  Scenario: The trash bin is empty if there are no soft removals at all

    Then I expect the trash bin for workspace "user-workspace" to contain the following items:
      | nodeAggregateId | userId | deleteTime | affectedDimensionSpacePoints |

    And I expect the trash bin for workspace "review-workspace" to contain the following items:
      | nodeAggregateId | userId | deleteTime | affectedDimensionSpacePoints |

  Scenario: Unpublished soft removals show up in the user workspace but not in its parent

    When the current date and time is "2025-06-24T17:56:25+02:00"

    And the command TagSubtree is executed with payload:
      | Key                          | Value                |
      | workspaceName                | "user-workspace"     |
      | nodeAggregateId              | "nodingers-cat"      |
      | coveredDimensionSpacePoint   | {"example":"source"} |
      | nodeVariantSelectionStrategy | "allSpecializations" |
      | tag                          | "removed"            |
    Then I expect the trash bin for workspace "user-workspace" to contain the following items:
      | nodeAggregateId | userId                     | deleteTime                | affectedDimensionSpacePoints              |
      | nodingers-cat   | initiating-user-identifier | 2025-06-24T15:56:25+00:00 | [{"example":"source"},{"example":"special"}] |

    And I expect the trash bin for workspace "review-workspace" to contain the following items:
      | nodeAggregateId | userId | deleteTime | affectedDimensionSpacePoints |



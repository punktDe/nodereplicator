# PunktDe NodeReplicator

[![Latest Stable Version](https://poser.pugx.org/punktDe/nodereplicator/v/stable)](https://packagist.org/packages/punktDe/nodereplicator) [![Total Downloads](https://poser.pugx.org/punktDe/nodereplicator/downloads)](https://packagist.org/packages/punktDe/nodereplicator) [![License](https://poser.pugx.org/punktDe/nodereplicator/license)](https://packagist.org/packages/punktDe/nodereplicator)

This package provides an additional option for the NodeType configuration to automatically update this node in other dimensions.

## The problem, this package solves

**Scenario:** On a multi language page, without configured fallbacks, you want to show addresses. The addresses are content nodes. An address entry should reference a country and a category. Country and categories should be content nodes too. While the title of a category and country needs to be translated for every language, the address itself stays the same for every language.

**The problem:** As soon, as you translate the page which contains the content nodes from language A to language B, you are no longer able to add nodes in both languages which have the same identifier. Without fallback you would need to add an address on every language separately with the references to the corresponding nodes.

**The solution:** This package adds a configuration option to the node type configuration called `replication`. Which allows you to specify if a node should be automatically replicated to all dimensions where the parent node already exists and also if the content in the alternative dimensions should be updated on change. 

## Configuration

**Options:**

| Option                                  | Description                                                                             |
|-----------------------------------------|-----------------------------------------------------------------------------------------|
| replication.structure                   | Automatically create and remove the node in other dimensions                            |
| replication.content                     | Automatically update the content of the corresponding nodes in other dimensions         |
| replication.updateEmptyPropertiesOnly   | When updating content, only update properties if they are empty in the target dimension |
| replication.createHidden                | Replicated nodes are created as hidden nodes                                            |
| replication.excludeProperties           | Do not update values of these properties in the target node                             |

**Example Configuration:**

```yaml
'Vendor.Package:Address':
  superTypes:
    'Neos.Neos:Content': true
  ...
  
  options:
    replication:
      structure: true
      content: true
    
'Vendor.Package:AddressCategory':
  superTypes:
    'Neos.Neos:Content': true
  ...
  
  options:  
    replication:
      structure: true
      
'Vendor.Package:AnotherAddressCategory':
  superTypes:
    'Neos.Neos:Content': true
  ...
  
  options:  
    replication:
      structure: true
      content: true
      updateEmptyPropertiesOnly: true
      
      
'Vendor.Package:TranslateableCategory':
  superTypes:
    'Neos.Neos:Content': true
  ...

  options:
    replication:
      structure: true
      content: true
      updateEmptyPropertiesOnly: true
      createHidden: true
    
'Vendor.Package:YetAnotherAddressCategory':
  superTypes:
    'Neos.Neos:Content': true
  ...
  
  options:  
    replication:
      structure: true
      content: true
      excludeProperties:
        - metaDescription
        - metaKeywords
```

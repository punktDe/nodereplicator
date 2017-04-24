# PunktDe NodeReplicator

This package provides an additional option for the node type configurations of the automatic replication nodes of a certain type. This provides dimensions in which the parent node exists, which makes it possible to store structured data like categories in content nodes. See the next sections for details.

## The problem, this package solves

**Scenario:** On a multi language page, without configured fallbacks, you want to show addresses. The addresses are content nodes. A address entry should reference a country and a category. Country and categories should be content nodes too. While the title of a category and country needs to be translated for every language, the address itself stays the same for every language.

**The problem:** As soon, as you translate the page which contains the content nodes from language A to language B, you are no longer able to add nodes in both languages which have the same identifier. Without fallback you would need to add an address on every language separately with the references to the corresponding nodes.

**The solution:** This package adds a configuration option to the node type configuration called `replication`. Which allows you to specify if a node should be automatically replicated to all dimensions where the parent node already exists and also if the content in the alternative dimensions should be updated on change. 

## Configuration

**Options:**

| Option                | Description                                                                     |
|-----------------------|---------------------------------------------------------------------------------|
| replication.structure | Automatically create and remove the node in other dimensions                    |
| replication.content   | Automatically update the content of the corresponding nodes in other dimensions |

**Example Configuration:**

```yaml
'Vendor.Package:Address':
  superTypes:
    'Neos.Neos:Content': true
  ...
  
  replication:
    structure: true
    content: true
    
'Vendor.Package:AddressCategory':
  superTypes:
    'Neos.Neos:Content': true
  ...
  
  replication:
    structure: true
    
```
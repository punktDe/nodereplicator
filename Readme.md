# PunktDe NodeReplicator

[![Latest Stable Version](https://poser.pugx.org/punktDe/nodereplicator/v/stable)](https://packagist.org/packages/punktDe/nodereplicator) [![Total Downloads](https://poser.pugx.org/punktDe/nodereplicator/downloads)](https://packagist.org/packages/punktDe/nodereplicator) [![License](https://poser.pugx.org/punktDe/nodereplicator/license)](https://packagist.org/packages/punktDe/nodereplicator)

This package provides an additional option for the NodeType configuration to automatically update this node in other dimensions.

## Requirements and Neos compatibility

Neos 9 introduces the event-sourced content repository. This package integrates with that stack via a **content graph catch-up hook** (instead of the signal-based interceptor used on Neos 8 and earlier). Replication work is **queued in the database** and should be processed asynchronously, for example with a cron job or worker that runs Flow CLI commands (see below).

**Node type configuration** (`options.replication` on your node types) is unchanged in spirit; only the runtime integration differs on Neos 9.

| `punktde/nodereplicator` | Neos CMS (as declared in `composer.json` for that release line) |
|--------------------------|------------------------------------------------------------------|
| **1.x** | 3.x or 4.x |
| **2.0.x – 2.2.x** | 5.x |
| **2.3.x – 3.0.1** | 5.x or 7.x |
| **3.0.2 – 3.0.x** | 5.x, 7.x, or 8.x |
| **4.x** | 9.x |

Use **4.x** for Neos 9 projects. Earlier versions do not support Neos 9.

### Neos 9: configuration and operations

- **Catch-up hook**: The package registers a hook under `Neos.ContentRepositoryRegistry.contentRepositories.<id>.contentGraphProjection.catchUpHooks` (see `Configuration/Settings.yaml` for the default `default` content repository). Override or merge if you use a non-default repository id.
- **Queue**: `PunktDe.NodeReplicator.queue.liveWorkspaceOnly` (default `true`) limits replication tasks to the live workspace when enabled.
- **CLI**: Process pending tasks with `./flow replication:processqueue`; remove finished rows with `./flow replication:removeprocessed`.
- **Persistence**: Neos 9 requires a **Doctrine migration** for the replication task entity (see `Migrations/` in this package); run your usual Flow migration workflow after upgrading.

## The problem, this package solves

**Scenario:** On a multi language page, without configured fallbacks, you want to show addresses. The addresses are content nodes. An address entry should reference a country and a category. Country and categories should be content nodes too. While the title of a category and country needs to be translated for every language, the address itself stays the same for every language.

**The problem:** As soon, as you translate the page which contains the content nodes from language A to language B, you are no longer able to add nodes in both languages which have the same identifier. Without fallback you would need to add an address on every language separately with the references to the corresponding nodes.

**The solution:** This package adds a configuration option to the node type configuration called `replication`. Which allows you to specify if a node should be automatically replicated to all dimensions where the parent node already exists and also if the content in the alternative dimensions should be updated on change.

## Configuration

**Options:**

```yaml
    'Vendor.Package:Address':
      superTypes:
        'Neos.Neos:Content': true

    ...

      options:
        replication:
          structure:
            // Replicate the node itself into the other dimension if the parent exists and the node is missing, default: false
            create: true
            // Create the node as hidden, default: false
            createHidden: true
            // Also delete the node in other dimensions, default: false
            remove: true

          // Defaults for all properties of this nodeType
          properties:
            // defaults false
            update: true

            // default false, takes precedence of update
            updateEmptyOnly: true

       properties:
         myProperty:
           options:
             replication:
               // default inherited from node level config
               update: false
               // default inherited from node level config
               updateEmptyOnly: false
```


**Example Configuration:**

```yaml
'Vendor.Package:Address':
  superTypes:
    'Neos.Neos:Content': true
  ...

  options:
    replication:
      structure:
        create: true
      properties:
        update: true

'Vendor.Package:AddressCategory':
  superTypes:
    'Neos.Neos:Content': true
  ...

  options:
    replication:
        structure:
            create: true

'Vendor.Package:AnotherAddressCategory':
  superTypes:
    'Neos.Neos:Content': true
  ...

  options:
      replication:
          structure:
              create: true
          properties:
              updateEmptyOnly: true


'Vendor.Package:TranslateableCategory':
  superTypes:
    'Neos.Neos:Content': true
  ...

  options:
      replication:
          structure:
              createHidden: true
          properties:
              updateEmptyOnly: true

# Disable a single property

'Vendor.Package:YetAnotherAddressCategory':
  superTypes:
    'Neos.Neos:Content': true
  ...

  options:
      replication:
          structure:
              create: true
          properties:
              update: true
  properties:
      metaDescription:
        options:
          replication:
            update: false
```

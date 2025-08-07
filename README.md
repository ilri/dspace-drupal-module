# CGSpace Drupal Importer Module

## Requirements
This module requires the third-party module  [drupal/country](https://www.drupal.org/project/country)

## Installation
If you're using composer add *ilri/cgspace_importer*
to your composer.json file under the repositories section.

Example:

```json
{
  "repositories": {
    "ilri/cgspace_importer": {
      "type": "vcs",
      "url": "git@github.com:ilri/dspace-drupal-module.git"
    }
  }
}
```
If you correctly added the repository as described above
you can now run in your shell:

```bash
composer require ilri/cgspace_importer
```

If everything has been done correctly, the latest version of the module is downloaded
and added to your installation.

You can now enable the module with Drush:

```bash
drush en cgspace_importer
```

or directly from the *admin/modules* page.

## Configuration

Once the module is installed you need to configure it at the general settings page `admin/config/cgspace/settings/general`:
- *Endpoint*: the CGSpace endpoint `Ex: https://dspace7test.ilri.org`
- *Importer ID*: Your identifier for statistical purposes that will be added at any Request to CGSpace `Ex: CGIAR`
- *CGSpace discover query Page Size*: The CGSpace API Page size for the queries, you can leave it at its default value or tune it depending on your system performance. `default: 100`
- *Chunk size of nodes to be processed at once*: The amount of nodes to be processed at each batch run for commands using the --all option, you can leave it at its default value or tune it depending on your system performance. `default: 50`
- *DEBUG mode*: If checked response times for any CGSpace request are logged. It could be used to measure performance. `default: disabled`

  > **Do NOT USE on Production!**

If you want to import everything from CGSpace you may skip this step; otherwise, you need to *first* select the Communities and then the Collections you want to import:
- *Communities*: `admin/config/cgspace/settings/communities`
- *Collections*: `admin/config/cgspace/settings/collections`

Last but not least you need to configure the Processors: `admin/config/cgspace/settings/processors`
where you can decide to use the module embedded vocabularies or map with terms on your existing installation vocabularies.

## First run
Once everything is configured you need to run the first import that will sync CGSpace items with your Drupal nodes. Currently, only a Drush command is provided:

`cgspace_importer:create`

and you can run it using the --all option that will import all CGSpace items in the Sitemap Index (ignoring your Communities and Collections settings)
or without that option that will import through the CGSpace API the items on Communities and Collections you selected.

### Examples

- Import all CGSpace items on Sitemap Index:

  ```bash
  drush cgspace_importer:create --all
  ```

-  Import CGSpace items in Communities and Collections you configured using the CGSpace API:

  ```bash
  drush cgspace_importer:create
  ```
## Drush commands
This module provides the following Drush commands:
- cgspace_importer:create [--all]

  Create Publication nodes by generating the list through the CGSpace API according to communities and collections selected on the configuration page.
  - --all

    if --all option is specified the list of Publication nodes is generated from CGSpace sitemap index
- cgspace_importer:update [YYYY-MM-DD] [--all]

  Update or create Publication nodes by generating the list through the CGSpace API according to communities and collections selected on the configuration page.

  If a date argument is specified, the list of updated or added items is generated accordingly
  - --all

    if --all option is specified the list of Publication nodes is generated through CGSpace API without taking care of collections and communities selected on the configuration page.
- cgspace_importer:delete [--all]

  Delete Publication nodes comparing with the list of items generated through the CGSpace API according to communities and collections selected on the configuration page.
  - --all

    if --all option is specified the Publication nodes are compared to the list of items generated from the CGSpace sitemap index.


### Examples
- Import all CGSpace publications starting from CGSpace Sitemap Index

  ```bash
  drush cgspace_importer:create --all
  ```

- Import all CGSpace publications starting from configured Communities and Collections

  ```bash
  drush cgspace_importer:create
  ```

- Update all publications nodes using CGSpace Sitemap Index (ignoring the Communities and Collections configuration)

  ```bash
  drush cgspace_importer:update --all
  ```

- Update publications nodes using CGSpace API according to configured Communities and Collections.

  ```bash
  drush cgspace_importer:update
  ```

- Update from a specific date (Ex: January, 1st 2020)

  ```bash
  drush cgspace_importer:update 2020-01-01
  ```

  > **Tip**: if you use a very old date as an argument, the behavior is similar to running create. ***Use carefully***!
- Remove Publication nodes using CGSpace API according to configured Communities and Collections.

  ```bash
  drush cgspace_importer:delete
  ```

  > **Tip**: if you don't select any collections in the configuration you can use this command to remove all publication nodes from your Drupal installation.
- Clean all publication nodes using the Sitemap Index.

  ```bash
  drush cgspace_importer:delete --all
  ```

## Cron jobs and update
You can manage the update functionalities using your system cron with the `drush cgspace_importer:update` command
in addition you can also trigger it manually through the CGSpace Sync page `admin/content/cgspace`.

**IMPORTANT**: we suggest to run the update functionality:
- once per day in the case of *full import* with --all option
- once per week in case of *partial import* with configured Communities and Collections

Since we have some limitations from the current CGSpace API we're able to get added/updated items from the `drush cgspace_importer:update` command but not the removed ones.
This means that you need to run periodically the delete `drush cgspace_importer:delete` command as well.

**IMPORTANT**: we suggest to run the delete functionality:
- once per day in the case of *full import* with --all option
- once per week in case of *partial import* with configured Communities and Collections


# Data Repository Connector

Connect and import records to an Omeka S instance from the following data repositories, optionally importing files:

- [Dataverse](https://dataverse.org)
- [Zenodo](https://zenodo.org)
- [Invenio](https://inveniosoftware.org)
- [CKAN](https://ckan.org)

Once imported, these items can be updated at any time by re-running the original import from the Past Imports page.

This module is available to users at the Global Admin and Supervisor levels.

Regardless of the service you have selected, you will be provided with the following options:

- Limit: The maximum number of results to retrieve in a single batch. If you notice errors or missing data, try lowering this number. Increasing it might make imports faster. (This field is required. Default is 100.)
- Test import: If checked, the Connector will ONLY import the number of results indicated in Limit field above. This is useful for previewing the metadata mapping from the source to Omeka S, and to ensure the connection works.
- Import files into Omeka S: If checked, media associated with a record will be imported into Omeka S. If not, no media will be included in the import.
- Comment: A note about the purpose or source of this import. This will appear on the Past Imports page and can be helpful to track your progress.
- Item sets: The items sets to import items into.
- Sites: The sites to import items into. You may see a default site appear here.

To update resources created using the Data Repository Connector, simply "re-run" an import from the same source. The resources will be updated, not re-imported. This allows you to use the Connector to sync data between your data repositories and the Omeka S installation. Go to the Past Imports page. Under the "Re-run" column, check the box for each import you wish to update, then press "Submit". Note: Re-running an import will erase any metadata modifications you have made to the items since the original import.

See the [Omeka S user manual](http://omeka.org/s/docs/user-manual/modules/datarepositoryconnector/) for user documentation.

## Installation

See general end user documentation for [Installing a module](http://omeka.org/s/docs/user-manual/modules/#installing-modules)

# Copyright
DataRepositoryConnector is Copyright © 2015-present Corporation for Digital Scholarship, Vienna, Virginia, USA http://digitalscholar.org

The Corporation for Digital Scholarship distributes the Omeka source code
under the GNU General Public License, version 3 (GPLv3). The full text
of this license is in the license file.

The Omeka name is a registered trademark of the Corporation for Digital Scholarship.

Third-party copyright in this distribution is noted where applicable.

All rights not expressly granted are reserved.

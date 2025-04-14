# If You Decide to Add Your Own Data Storage Implementation

You should follow these steps:

1. Make changes to the ensureConnector() method of the DatabaseController.php file

2. Create a directory with your database type. Note that the first letter of the directory name must be capitalized.

3. Inside the new directory, implement the Connector and UpgraderMapper classes as they are implemented in Mariadb. With the Connector class you should implement all abstract methods, and with the UpgrageMapper class you should implement all methods specified in the corresponding interface.

4. All methods to execute queries to your database must be done by implementing interfaces (\*MapperInterface.php) or by extending classes from the Common directory. The new implementations should be placed in the new directory. Note that the names of your classes must not contain the Common prefix.

## Some remarks:

- Do not put database-specific code into classes in the Common directory.

- Do not use table names in queries directly. Instead, use the tablePrefix method from the Connector class.

- If you need to make minor edits to a particular Mapper, consider extending the corresponding class from the Common directory instead of implementing the interface.

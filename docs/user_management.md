# User Management

DmarcSrg supports the ability to add new users with different access levels, and to assign domains to users.

# Configuration

Before activating user management, make sure that the administrator has a password (config/config.php -> admin -> password).
In the config.php set users -> user_management to true. During authorization, in addition to the password, DmarcSrg will ask for a username.
To log in with an administrator account, use username `admin` and the password from the configuration file.

# Usage

User management is available in the web interface, Administration -> Users.

When adding a user, you must specify the user name, access level, and domains. The password can be set after adding a user, in edit mode.

Multiple domains can be assigned to a single user and multiple users can be assigned the same domain. There are no restrictions other than common sense.

Each user has its own settings.

> [!NOTE]
> You do not need to add the Admin user. It is a built-in account. Use the user name `admin` in the authentication dialog.

# Access Levels

There are currently three levels of access: 'admin', 'manager', 'user'. Only the last two ones can be assigned to new users. The access levels affect the functionality available to users in the web interface:


|           Action           |  Admin  | Manager |  User   |
|----------------------------|:-------:|:-------:|:-------:|
| _*Incoming reports*_       |         |         |         |
| View Report list           |   Yes   |   Yes   |   Yes   |
| View report                |   Yes   |   Yes   |   Yes   |
| Load from local file       |   Yes   |   Yes   |   No    |
| Load from mailboxes        |   Yes   |   No    |   No    |
| Load from server directory |   Yes   |   No    |   No    |
| Load from remote FS        |   Yes   |   No    |   No    |
| _*Summary reports*_        |         |         |         |
| Create and view reports    |   Yes   |   Yes   |   Yes   |
| _*Domains*_                |         |         |         |
| View domains               |   Yes   |   Yes   |   Yes   |
| Add domains                |   Yes   | Yes [^1]|   No    |
| Delete domains             |   Yes   | Yes [^2]|   No    |
| Edit domains               |   Yes   |   Yes   |   No    |
| Assign/unassign domains    |   Yes   |   No    |   No    |
| _*Users*_                  |         |         |         |
| View users                 |   Yes   |   No    |   No    |
| Add users                  |   Yes   |   No    |   No    |
| Delete users               |   Yes   |   No    |   No    |
| Edit users                 |   Yes   |   No    |   No    |
| _*Other actions*_          |         |         |         |
| Database management        |   Yes   |   No    |   No    |
| Logs                       |   Yes   |   No    |   No    |

[^1]: The domain will be added (if necessary) and assigned to the user. It is possible to enforce domain ownership verification via checking a DNS TXT record.
[^2]: Instead of deleting, a domain will be unassigned from the user. Admin will be able to finally remove this domain.

# Domain Verification

DmarcSrg can perform ownership verification before adding a domain. To activate the verification it is necessary to set the `users -> domain_verification` parameter to 'dns' in the configuration file. While adding a domain, the dialog box will display a warning with information about domain verification.

To deactivate verification set the parameter value to 'none'.

> [!NOTE]
> No domain verification is performed for the `admin` user.

# Utils

utils/summary_report.php now has a parameter `user` that allows you to get summary reports for all domains of a specified user.

For example:
`$ php utils/summary_report.php domain=all user=frederick1 period=lastmonth`
will send a summary report for the last month for all domains assigned to user frederick1 via email.

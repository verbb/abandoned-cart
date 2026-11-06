# Permissions

Abandoned Cart's permissions let you give staff the access their role needs. A customer service team might look up customers and add them to Do Not Email, a marketing manager might write and test sequences, and a developer might keep an eye on System Status. Set them in **Settings → Users → User Groups**, or on an individual user's **Permissions** tab, under the plugin's heading.

| Permission | What It Allows |
| --- | --- |
| Access Abandoned Cart | View the Overview, Carts, Customers and Sequences screens, and the plugin's tab on user accounts. |
| Manage sequences and send emails | Create, edit, reorder, turn on and off, and delete sequences and their emails; preview emails and send tests; use **Send Now**, **Stop Emails** and **Retry** on a cart; and use **Run Now** in System Status. |
| Manage Do Not Email | Add customers and addresses to Do Not Email, and remove them. |
| View System Status | Open **Settings → System Status**, without access to the other settings. |

**Access Abandoned Cart** is Craft's standard permission for reaching a plugin's section, and anyone given one of the others needs it too. Someone with only that permission can see everything on the Overview, Carts, Customers and Sequences screens, but can't change anything or send email.

**General Settings** and **Email Templates** are for administrators only. Both are stored in Craft's project config, which is why they follow Craft's rules for admin changes rather than a permission.

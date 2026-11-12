# Permissions

Abandoned Cart's permissions let you give staff the access their role needs. A customer service team might look up customers and add them to Do Not Email, a marketing manager might write and test sequences, and a developer might keep an eye on System Status. Set them in **Settings → Users → User Groups**, or on an individual user's **Permissions** tab.

**Access Abandoned Cart** is listed under **General**, with Craft's other permissions for reaching each plugin's section. The other three are listed under the plugin's own heading, which shows the plugin's name in the control panel, **Abandoned Carts** unless you've changed **Plugin Name** in General Settings.

| Permission | What It Allows |
| --- | --- |
| Access Abandoned Cart | View the Overview, Carts, Customers and Sequences screens, and the plugin's tab on user accounts. |
| Manage sequences and send emails | Create, edit, reorder, turn on and off, and delete sequences and their emails. Open, preview and test emails. Use **Send Now**, **Stop Emails** and **Retry** on a cart, and **Run Now** in System Status. |
| Manage Do Not Email | Add customers and addresses to Do Not Email, and remove them. |
| View System Status | Open **Abandoned Carts → Settings → System Status**, without access to the other settings. |

Anyone given one of the other permissions needs **Access Abandoned Cart** too. Someone with only that permission can see the Overview, Carts, Customers and Sequences screens, but can't change anything or send email. They can see each sequence's audience and email timeline, but opening an email to read its wording or preview it needs **Manage sequences and send emails**. The Overview's link to System Status also needs **View System Status**.

**General Settings**, **Email Templates** and **Sequence Presets** are for administrators only. All three are stored in Craft's project config, which is why they follow Craft's rules for admin changes rather than a permission.

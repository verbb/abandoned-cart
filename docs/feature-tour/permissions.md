# Permissions

Abandoned Cart's permissions let you give staff the access their role needs. A customer service team might look up customers, add them to Do Not Email and handle requests to erase their details, a marketing manager might write and test sequences, and a developer might keep an eye on System Status. Set them in **Settings → Users → User Groups**, or on an individual user's **Permissions** tab.

**Access Abandoned Cart** is listed under **General**, with Craft's other permissions for reaching each plugin's section. The other four are listed under the plugin's own heading, which shows the plugin's name in the control panel, **Abandoned Carts** unless you've changed **Plugin Name** in General Settings.

| Permission | What It Allows |
| --- | --- |
| Access Abandoned Cart | View the Overview, Carts, Customers and Sequences screens, and the plugin's tab on user accounts. |
| Manage sequences and send emails | Create, edit, reorder, turn on and off, and delete sequences and their emails. Open, preview and test emails. Use **Send Now**, **Stop Emails** and **Retry** on a cart, and **Run Now** in System Status. |
| Manage Do Not Email | Add customers and addresses to Do Not Email, and remove them. |
| Delete customers | Delete a customer's record and all their cart history from their customer page, for erasure requests. |
| View System Status | Open **Abandoned Carts → Settings → System Status**, without access to the other settings. |

Anyone given one of the other permissions needs **Access Abandoned Cart** too. Someone with only that permission can see the Overview, Carts, Customers and Sequences screens, but can't change anything or send email. They can see each sequence's audience and email timeline, but opening an email to read its wording or preview it needs **Manage sequences and send emails**. The Overview's setup checklist hides its test and **Turn On** buttons from them, and the Overview's link to System Status needs **View System Status** as well.

For a customer service team, **Manage Do Not Email** stops a customer's emails without giving access to the sequences, and **Delete customers** handles erasure requests. Keep **Manage sequences and send emails** for whoever looks after the emails' wording, because it also allows deleting sequences and sending emails straight away. Deleting a customer can't be undone and removes their history from the reports, so give it only to the people who handle erasure requests.

**General Settings**, **Email Templates** and **Sequence Presets** are for administrators only. All three are stored in Craft's project config, which is why they follow Craft's rules for admin changes rather than a permission.

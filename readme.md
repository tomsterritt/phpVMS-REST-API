# phpVMS REST API

This is a basic REST API module for access to phpVMS data and functionality, for example in third party clients.

You can find a list of existing features/resources below as well as a (rather long and ever expanding) to do list - all contributions are welcome.

__Important note:__ Clients will make requests directly to your own website and require user credentials. While clients should make every attempt to keep credentials secure you should be wary of where you enter your password when using clients built around this API.

### Requirements
- [SimpleNews](https://github.com/tomsterritt/simplenews) (__Note:__ you don't need to use SimpleNews front end - just ensure NewsData.class.php is available)

### Installation
Simply upload the files within the [core](/core) folder to your phpVMS root.

### Features & Resources
- Username/password user auth for all requests via Basic Auth
- Only available to confirmed registered users
- Removes sensitive information from responses (encrypted password, salt, emails, IP addresses)
- All PUT/POST data is expected to be JSON
- Modular API resources - easy for other modules to include API functionality

#### News
Resource URI          | HTTP Method | Data  | Purpose
--------------------- | ----------- | ----- | -------
/api/news[?page=n]    | GET         | N/A   | News list [at page n]
/api/news/{NewsID}    | GET         | N/A   | Individual news item

#### Pilots
Resource URI          | HTTP Method | Data  | Purpose
--------------------- | ----------- | ----- | -------
/api/pilots           | GET         | N/A   | Pilot list
/api/pilots/{PilotID} | GET         | N/A   | Individual pilot details
/api/pilots/me        | GET         | N/A   | Current pilot (from Auth header)

#### Registrations
Available only to users who can manage registrations

Resource URI                   | HTTP Method | Data              | Purpose
------------------------------ | ----------- | ----------------- | -------
/api/registrations             | GET         | N/A               | List pending registrations
/api/registrations/{RequestID} | PUT         | {confirmed:[1/2]} | Approve/Reject registration

#### Schedules
Resource URI                | HTTP Method | Data | Purpose
--------------------------- | ----------- | ---- | -------
/api/schedules              | GET         | N/A  | List schedules (all of them)
/api/schedules/{ScheduleID} | GET         | N/A  | Individual schedule details

#### Bids
Resource URI | HTTP Method | Data   | Purpose
------------ | ----------- | ------ | -------
/api/bids    | POST        | {id:n} | Bid on schedule ID n


### To Do
Please let me know if there's anything missing from this list or you'd like to see included

- [ ] __Better auth method (token based?)__
- [x] ~~It's all in one file. Difficult to read. How can this be improved?~~
- [ ] Editing pilot profile
- [ ] View PIREPs
- [ ] Submit a PIREP?
- [ ] Awards
- [ ] Aircraft
- [ ] Paginate schedules list
- [ ] Airports/Hubs
- [ ] Viewing pages?

#### Admin

- [ ] Approve/Reject PIREPs
- [ ] Manage airlines
- [ ] Manage schedules
- [ ] Manage aircraft
- [ ] Manage airports
- [ ] Manage news
- [ ] Manage pages
- [ ] Manage downloads
- [ ] Send mass email
- [ ] Manage pilot groups
- [ ] Manage pilot ranks
- [ ] Manage awards
- [ ] Financials
- [ ] Site settings
- [ ] Admin logs
- [ ] Maintenance options

### Licence
Released under the [&#9786; Licence](http://licence.visualidiot.com/)

Feel free to edit this code however you want. Please also don't hesitate to fork this repo and open pull requests in order to extend the module however you feel is appropriate.

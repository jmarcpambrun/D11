# Time-based One-Time Password (TOTP)

## Deployment requirements

### Time(clock) accuracy
The TOTP plugin requires the Server and Client clocks to be closely synchronized.

Should the Server and Client clock difference combined with user input delay extend beyond the allowed Accepted Codes time authentication tokens will fail to validate. 

The server should be configured to utilize Network Time Protocol (NTP) or other time synchronization solutions.

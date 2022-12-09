FROM anzusystems/php:1.0.0-php81-cli

#
### Basic arguments and variables
ARG DOCKER_USER_ID
ARG DOCKER_GROUP_ID
#
### Change USER_ID and GROUP_ID for nonroot container user if needed
RUN create-user ${DOCKER_USER_ID} ${DOCKER_GROUP_ID}
#
### Scan ssh.dev.azure.com for ssh keys and basic user setup
USER user

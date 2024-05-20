#!/bin/bash

PLUGIN_DIR=$(pwd)
PLUGINSLUG="runthings-secrets"
BUILD_DIR="${PLUGIN_DIR}/build"
DISTIGNORE="${PLUGIN_DIR}/.distignore"
MAKEPOT_SCRIPT="${PLUGIN_DIR}/bin/makepot.sh"

# Function to check for required tools
check_tool() {
  if ! command -v $1 &> /dev/null; then
    echo "Error: $1 is not installed."
    exit 1
  fi
}

# Check for required tools
check_tool rsync
check_tool zip
check_tool mktemp

# Check if the script is being run from the root directory of the plugin
if [[ ! -f "${PLUGIN_DIR}/${PLUGINSLUG}.php" ]]; then
  echo "Error: This script should be run from the root directory of the plugin."
  echo "Make sure you are in the ${PLUGINSLUG} directory and run the script as ./bin/build-zip.sh"
  exit 1
fi

# Run makepot.sh to generate/update translation files
echo "Running makepot.sh to generate/update translation files..."
if ! ${MAKEPOT_SCRIPT}; then
  echo "Error: makepot.sh failed."
  exit 1
fi

# Create the build directory if it doesn't exist
echo "Creating build directory..."
mkdir -p ${BUILD_DIR}

# Create a temporary directory to stage the files to be zipped
TEMP_DIR=$(mktemp -d)
echo "Created temporary directory at ${TEMP_DIR}"

# Function to clean up the temporary directory
cleanup() {
  echo "Cleaning up temporary directory..."
  rm -rf "${TEMP_DIR}"
  echo "Temporary directory cleaned up."
}

# Ensure the cleanup function is called on script exit
trap cleanup EXIT

# Copy all files to the temporary directory, excluding the patterns in .distignore
echo "Copying files to temporary directory, excluding patterns in .distignore..."
if ! rsync -av --exclude-from=${DISTIGNORE} ${PLUGIN_DIR}/ ${TEMP_DIR}/; then
  echo "Error: rsync failed."
  exit 1
fi

# Create the zip file from the temporary directory
cd ${TEMP_DIR}
echo "Creating zip file..."
if ! zip -r ${BUILD_DIR}/${PLUGINSLUG}.zip .; then
  echo "Error: zip failed."
  exit 1
fi
echo "Zip file created at ${BUILD_DIR}/${PLUGINSLUG}.zip"

# Clean up the temporary directory
cd ${PLUGIN_DIR}

echo "Build completed successfully."

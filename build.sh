#!/bin/bash
#
# Build script for The Simplest Analytics WordPress plugin
# Creates a release-ready .zip file
#

set -e

# Plugin info
PLUGIN_SLUG="the-simplest-analytics"
VERSION=$(grep -m1 "* Version:" the-simplest-analytics.php | sed 's/.*Version:[[:space:]]*//')

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Create build directory
BUILD_DIR="build"
RELEASE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

# Clean previous build
rm -rf "${BUILD_DIR}"
mkdir -p "${RELEASE_DIR}"

# Files and directories to include
INCLUDE=(
    "the-simplest-analytics.php"
    "uninstall.php"
    "readme.txt"
    "includes"
    "templates"
    "assets"
)

# Copy files to build directory
for item in "${INCLUDE[@]}"; do
    if [ -e "$item" ]; then
        cp -r "$item" "${RELEASE_DIR}/"
        echo "  ✓ Copied: $item"
    else
        echo -e "${RED}  ✗ Missing: $item${NC}"
    fi
done

# Create the zip file
cd "${BUILD_DIR}"
ZIP_FILE="${PLUGIN_SLUG}-${VERSION}.zip"
zip -r "../${ZIP_FILE}" "${PLUGIN_SLUG}" -x "*.DS_Store" -x "*__MACOSX*"
cd ..

# Cleanup build directory
rm -rf "${BUILD_DIR}"

# Output results
if [ -f "${ZIP_FILE}" ]; then
    SIZE=$(du -h "${ZIP_FILE}" | cut -f1)
    echo ""
    echo -e "${GREEN}✓ Build complete!${NC}"
    echo "  File: ${ZIP_FILE}"
    echo "  Size: ${SIZE}"
    echo ""
else
    echo -e "${RED}✗ Build failed!${NC}"
    exit 1
fi

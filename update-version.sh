#!/bin/bash

# Update Version Script
# Usage: ./update-version.sh 1.0.1 "Bug fixes and improvements"

if [ $# -ne 2 ]; then
    echo "Usage: $0 <version> <changelog>"
    echo "Example: $0 1.0.1 'Bug fixes and improvements'"
    exit 1
fi

NEW_VERSION=$1
CHANGELOG=$2

echo "Updating to version $NEW_VERSION..."

# Update main plugin file
sed -i.bak "s/Version: [0-9]\+\.[0-9]\+\.[0-9]\+/Version: $NEW_VERSION/" woo-customer-orders-report.php

# Update version.json
cat > version.json << EOF
{
    "version": "$NEW_VERSION",
    "changelog": "$CHANGELOG"
}
EOF

# Update updater initialization
sed -i.bak "s/'[0-9]\+\.[0-9]\+\.[0-9]\+'/'$NEW_VERSION'/" woo-customer-orders-report.php

echo "Version updated to $NEW_VERSION"
echo "Don't forget to:"
echo "1. Test your changes"
echo "2. git add ."
echo "3. git commit -m 'Version $NEW_VERSION: $CHANGELOG'"
echo "4. git tag v$NEW_VERSION"
echo "5. git push origin main --tags"
echo "6. Create a GitHub release" 
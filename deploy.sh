#!/bin/bash
# ========================================
# Retro Echo Records 数据库部署脚本
# ========================================

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 数据库配置
DB_NAME="retro_echo"
DB_USER="root"
DB_PASS=""
DB_HOST="127.0.0.1"

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}  Retro Echo Records 数据库部署  ${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

# 检查MySQL是否运行
echo -e "${YELLOW}[1/9] 检查MySQL服务...${NC}"
if ! mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -e "SELECT 1" &> /dev/null; then
    echo -e "${RED}✗ MySQL服务未运行或连接失败${NC}"
    exit 1
fi
echo -e "${GREEN}✓ MySQL服务正常${NC}"
echo ""

# 备份现有数据库
echo -e "${YELLOW}[2/9] 备份现有数据库...${NC}"
BACKUP_FILE="backups/backup_${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql"
mkdir -p backups
if mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -e "USE $DB_NAME" 2>/dev/null; then
    mysqldump -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_FILE
    echo -e "${GREEN}✓ 备份已保存: $BACKUP_FILE${NC}"
else
    echo -e "${YELLOW}! 数据库不存在，跳过备份${NC}"
fi
echo ""

# 询问部署方式
echo -e "${YELLOW}[3/9] 选择部署方式${NC}"
echo "1) 全新部署 (删除并重建数据库)"
echo "2) 仅更新 (保留数据，更新架构)"
echo "3) 取消"
read -p "请选择 [1-3]: " deploy_option

case $deploy_option in
    1)
        echo -e "${GREEN}→ 执行全新部署${NC}"
        FRESH_DEPLOY=true
        ;;
    2)
        echo -e "${GREEN}→ 执行更新部署${NC}"
        FRESH_DEPLOY=false
        ;;
    3)
        echo -e "${YELLOW}部署已取消${NC}"
        exit 0
        ;;
    *)
        echo -e "${RED}无效选项${NC}"
        exit 1
        ;;
esac
echo ""

# 重建或更新数据库
if [ "$FRESH_DEPLOY" = true ]; then
    echo -e "${YELLOW}[4/9] 重建数据库...${NC}"
    mysql -h$DB_HOST -u$DB_USER -p$DB_PASS <<EOF
DROP DATABASE IF EXISTS $DB_NAME;
CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF
    echo -e "${GREEN}✓ 数据库已重建${NC}"
else
    echo -e "${YELLOW}[4/9] 检查数据库存在...${NC}"
    mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo -e "${GREEN}✓ 数据库检查完成${NC}"
fi
echo ""

# 导入数据库架构
echo -e "${YELLOW}[5/9] 导入数据库架构...${NC}"
if [ -f "sql/schema.sql" ]; then
    mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < sql/schema.sql
    echo -e "${GREEN}✓ 架构已导入${NC}"
else
    echo -e "${RED}✗ 找不到 sql/schema.sql${NC}"
    exit 1
fi
echo ""

# 创建视图
echo -e "${YELLOW}[6/9] 创建视图...${NC}"
if [ -f "sql/views.sql" ]; then
    mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < sql/views.sql
    echo -e "${GREEN}✓ 视图已创建${NC}"
else
    echo -e "${YELLOW}! 找不到 sql/views.sql，跳过${NC}"
fi
echo ""

# 创建存储过程
echo -e "${YELLOW}[7/9] 创建存储过程...${NC}"
if [ -f "sql/procedures.sql" ]; then
    mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < sql/procedures.sql
    echo -e "${GREEN}✓ 存储过程已创建${NC}"
else
    echo -e "${YELLOW}! 找不到 sql/procedures.sql，跳过${NC}"
fi
echo ""

# 创建触发器
echo -e "${YELLOW}[8/9] 创建触发器...${NC}"
if [ -f "sql/triggers.sql" ]; then
    mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < sql/triggers.sql
    echo -e "${GREEN}✓ 触发器已创建${NC}"
else
    echo -e "${YELLOW}! 找不到 sql/triggers.sql，跳过${NC}"
fi
echo ""

# 创建索引
echo -e "${YELLOW}[9/9] 创建索引...${NC}"
if [ -f "sql/indexes.sql" ]; then
    mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < sql/indexes.sql
    echo -e "${GREEN}✓ 索引已创建${NC}"
else
    echo -e "${YELLOW}! 找不到 sql/indexes.sql，跳过${NC}"
fi
echo ""

# 导入测试数据（可选）
if [ "$FRESH_DEPLOY" = true ] && [ -f "sql/seeds.sql" ]; then
    read -p "是否导入测试数据？[y/N]: " import_seeds
    if [[ $import_seeds =~ ^[Yy]$ ]]; then
        mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME < sql/seeds.sql
        echo -e "${GREEN}✓ 测试数据已导入${NC}"
        echo ""
    fi
fi

# 验证部署
echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}  部署统计  ${NC}"
echo -e "${BLUE}======================================${NC}"
TABLE_COUNT=$(mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")
VIEW_COUNT=$(mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -se "SELECT COUNT(*) FROM information_schema.views WHERE table_schema='$DB_NAME'")
PROC_COUNT=$(mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -se "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='$DB_NAME' AND routine_type='PROCEDURE'")
FUNC_COUNT=$(mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -se "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='$DB_NAME' AND routine_type='FUNCTION'")
TRIGGER_COUNT=$(mysql -h$DB_HOST -u$DB_USER -p$DB_PASS -se "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='$DB_NAME'")

echo "  表数量: $TABLE_COUNT"
echo "  视图数量: $VIEW_COUNT"
echo "  存储过程: $PROC_COUNT"
echo "  函数: $FUNC_COUNT"
echo "  触发器: $TRIGGER_COUNT"
echo ""

echo -e "${GREEN}======================================${NC}"
echo -e "${GREEN}  部署完成！  ${NC}"
echo -e "${GREEN}======================================${NC}"
echo ""
echo "接下来:"
echo "  1. 访问 http://localhost/Database/public/"
echo "  2. 查看文档: IMPROVEMENTS_SUMMARY.md"
if [ -n "$BACKUP_FILE" ] && [ -f "$BACKUP_FILE" ]; then
    echo "  3. 备份文件: $BACKUP_FILE"
fi
echo ""

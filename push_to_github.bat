@echo off
echo =======================================================
echo     DANG DAY CODE LEN GITHUB - NHK MOBILE PROJECT
echo =======================================================

:: 1. Khoi tao Git neu chua co
if not exist .git (
    echo [*] Dang khoi tao Git repository...
    git init
)

:: 2. Add tat ca file
echo [*] Dang chuan bi du lieu...
git add .

:: 3. Commit
echo [*] Dang tao ban ghi commit...
set /p msg="Nhap ghi chu commit (mac dinh: 'Update project code'): "
if "%msg%"=="" set msg=Update project code
git commit -m "%msg%"

:: 4. Cau hinh remote (Xoa cai cu neu co va add cai moi)
git remote remove origin >nul 2>&1
echo [*] Dang ket noi voi GitHub: https://github.com/24211tt2979-cmd/BE_Project.git
git remote add origin https://github.com/24211tt2979-cmd/BE_Project.git

:: 5. Push code (Dung nhanh main)
echo [*] Dang day code len...
git branch -M main
git push -u origin main --force

echo =======================================================
echo           HOAN THANH! NHAN PHIM BAT KY DE THOAT.
echo =======================================================
pause
